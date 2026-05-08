-- ============================================================
-- LandingAI Builder — Supabase Schema
-- Ejecutar en: Supabase Dashboard → SQL Editor → New Query
-- ============================================================

-- ── EXTENSIONES ─────────────────────────────────────────────
create extension if not exists "uuid-ossp";
create extension if not exists "pgcrypto";


-- ── TABLA: profiles ─────────────────────────────────────────
-- Se crea automáticamente cuando un usuario se registra
create table if not exists public.profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  email       text,
  plan        text not null default 'free' check (plan in ('free','pro','agency')),
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

alter table public.profiles enable row level security;

-- El usuario solo puede leer/actualizar su propio perfil
create policy "profiles: select own" on public.profiles
  for select using (auth.uid() = id);

create policy "profiles: update own" on public.profiles
  for update using (auth.uid() = id);

-- Trigger para crear el perfil al registrarse
create or replace function public.handle_new_user()
returns trigger language plpgsql security definer as $$
begin
  insert into public.profiles (id, email)
  values (new.id, new.email)
  on conflict (id) do nothing;
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();


-- ── TABLA: api_keys ─────────────────────────────────────────
-- Guarda las API Keys de terceros del usuario (cifradas)
create table if not exists public.api_keys (
  id          uuid primary key default uuid_generate_v4(),
  user_id     uuid not null references auth.users(id) on delete cascade,
  provider    text not null check (provider in ('anthropic','openai','gemini')),
  key_enc     text not null,           -- valor cifrado con pgcrypto (si se usa)
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),
  unique (user_id, provider)
);

alter table public.api_keys enable row level security;

create policy "api_keys: select own" on public.api_keys
  for select using (auth.uid() = user_id);

create policy "api_keys: insert own" on public.api_keys
  for insert with check (auth.uid() = user_id);

create policy "api_keys: update own" on public.api_keys
  for update using (auth.uid() = user_id);

create policy "api_keys: delete own" on public.api_keys
  for delete using (auth.uid() = user_id);


-- ── TABLA: usage ────────────────────────────────────────────
-- Contador mensual de generaciones por usuario
create table if not exists public.usage (
  user_id     uuid not null references auth.users(id) on delete cascade,
  month       text not null,   -- formato: YYYY-MM (ej. 2025-06)
  count       integer not null default 0,
  updated_at  timestamptz not null default now(),
  primary key (user_id, month)
);

alter table public.usage enable row level security;

create policy "usage: select own" on public.usage
  for select using (auth.uid() = user_id);

-- El proxy usa service_role para leer/escribir, pero permitimos
-- que el frontend consulte su propio uso:
create policy "usage: insert own" on public.usage
  for insert with check (auth.uid() = user_id);

create policy "usage: update own" on public.usage
  for update using (auth.uid() = user_id);


-- ── FUNCIÓN RPC: increment_usage ───────────────────────────
-- Llamada desde proxy.php para contar generaciones atómicamente.
-- Usa security definer para que el proxy (con anon key + JWT) pueda
-- hacer upsert sin problemas de RLS.
create or replace function public.increment_usage(
  p_user_id uuid,
  p_month   text
)
returns void language plpgsql security definer as $$
begin
  insert into public.usage (user_id, month, count, updated_at)
  values (p_user_id, p_month, 1, now())
  on conflict (user_id, month)
  do update set
    count      = public.usage.count + 1,
    updated_at = now();
end;
$$;

-- Revocar acceso público y dar solo a roles autenticados + service_role
revoke all on function public.increment_usage(uuid, text) from public;
grant execute on function public.increment_usage(uuid, text) to authenticated;
grant execute on function public.increment_usage(uuid, text) to service_role;


-- ── FUNCIÓN RPC: get_usage ──────────────────────────────────
-- Devuelve el uso del mes actual del usuario autenticado.
create or replace function public.get_usage(p_month text default null)
returns integer language plpgsql security definer as $$
declare
  v_month text;
  v_count integer;
begin
  v_month := coalesce(p_month, to_char(now(), 'YYYY-MM'));
  select count into v_count
  from public.usage
  where user_id = auth.uid() and month = v_month;
  return coalesce(v_count, 0);
end;
$$;

grant execute on function public.get_usage(text) to authenticated;


-- ── FUNCIÓN: updated_at trigger ────────────────────────────
create or replace function public.set_updated_at()
returns trigger language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create trigger profiles_updated_at before update on public.profiles
  for each row execute procedure public.set_updated_at();

create trigger api_keys_updated_at before update on public.api_keys
  for each row execute procedure public.set_updated_at();

create trigger usage_updated_at before update on public.usage
  for each row execute procedure public.set_updated_at();


-- ── ÍNDICES ─────────────────────────────────────────────────
create index if not exists idx_api_keys_user on public.api_keys(user_id);
create index if not exists idx_usage_user_month on public.usage(user_id, month);


-- ── STRIPE (opcional) ───────────────────────────────────────
-- Cuando integres Stripe, actualiza el plan aquí desde el webhook.
-- Ejemplo de función que Stripe webhook puede llamar via service_role:
--
-- create or replace function public.set_user_plan(p_user_id uuid, p_plan text)
-- returns void language plpgsql security definer as $$
-- begin
--   update public.profiles set plan = p_plan where id = p_user_id;
-- end;
-- $$;
-- grant execute on function public.set_user_plan(uuid, text) to service_role;


-- ── VERIFICACIÓN ────────────────────────────────────────────
-- Ejecuta esto al final para confirmar que todo quedó bien:
-- select table_name from information_schema.tables where table_schema = 'public';
-- select routine_name from information_schema.routines where routine_schema = 'public' and routine_type = 'FUNCTION';
