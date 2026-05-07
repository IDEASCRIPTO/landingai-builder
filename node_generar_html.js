// CÓDIGO DEL NODO n8n "Generar HTML" — 3 temas
const body = $input.first().json.body || $input.first().json;
const brief = body.brief_agente || {};
const secciones_activas = body.secciones_activas || [];
const tema = Number(body.tema) || 1;
const tipo_salida = body.tipo_salida || 'html';
const NP = body.nombre_producto || brief.propuesta_valor_central || 'Producto';
const CP = body.color_primario || body.color_principal || '#6366F1';
const fuente = body.fuente || 'Inter';

function hexToRgb(h){
  const r=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(h);
  return r?[parseInt(r[1],16),parseInt(r[2],16),parseInt(r[3],16)]:[99,102,241];
}
const [pr,pg,pb] = hexToRgb(CP);

const fotos_hero = body.fotos_hero_procesadas || [];
const foto_problema = body.foto_problema_procesada || null;
const fotos_reviews = body.fotos_reviews_procesadas || [];
const foto_antes = body.imagen_antes || foto_problema || null;
const foto_despues = body.imagen_despues || (fotos_hero[0] || null);
const foto_bens = body.imagen_bens || null;
const hero_estilo = body.hero_estilo || 'A';
const reviews_estilo = Number(body.reviews_estilo) || 1;
const ratio_hero   = body.ratio_hero   || '4/3';
const ratio_antes  = body.ratio_antes  || '1/1';
const ratio_despues= body.ratio_despues|| '1/1';
const ratio_bens   = body.ratio_bens   || '4/3';
const ratio_prob   = body.ratio_prob   || '4/3';
const ratio_revs   = body.ratio_revs   || '1/1';

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function stars(n){ const s=Math.min(5,Math.max(1,n||5)); return '★'.repeat(s)+'☆'.repeat(5-s); }
function toEmbedUrl(url){
  if(!url) return '';
  const iframe = url.match(/<iframe[^>]+src=["']([^"']+)["']/i);
  if(iframe){
    let src = iframe[1];
    if(/vimeo/.test(src)) src += (src.includes('?')?'&':'?')+'autoplay=1&muted=1&loop=1';
    else if(/youtube/.test(src)) src += (src.includes('?')?'&':'?')+'autoplay=1&mute=1&rel=0&modestbranding=1';
    return src;
  }
  if(/player\.vimeo\.com/.test(url)) return url+(url.includes('?')?'&':'?')+'autoplay=1&muted=1&loop=1';
  const vim = url.match(/vimeo\.com\/(\d+)/);
  if(vim) return `https://player.vimeo.com/video/${vim[1]}?autoplay=1&muted=1&loop=1&title=0&byline=0&portrait=0&badge=0`;
  const yt = url.match(/(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
  if(yt) return `https://www.youtube.com/embed/${yt[1]}?autoplay=1&mute=1&rel=0&modestbranding=1&loop=1&playlist=${yt[1]}`;
  return url;
}

function normalizeCopy(raw){
  const c = raw || {};
  function toObj(v, tipo){
    if(!v && v!==0) return {};
    if(typeof v==='object' && !Array.isArray(v)) return v;
    try{ const p=JSON.parse(v); if(typeof p==='object' && !Array.isArray(p)) return p; }catch(e){}
    const lines = String(v).split('\n').filter(l=>l.trim());
    switch(tipo){
      case 'hero': return {titulo:lines[0]||'',sub:lines[1]||'',cta:brief.cta_recomendado||'Comprar ahora',badge:''};
      case 'problema': return {titulo:lines[0]||'',desc:lines.slice(1).join(' ')||lines[0]||''};
      case 'garantia': return {titulo:lines[0]||'100% Garantizado',desc:lines.slice(1).join(' ')||String(v)};
      case 'cta_final': return {titulo:lines[0]||'',sub:lines[1]||'',btn:brief.cta_recomendado||'Comprar ahora',escasez:''};
      case 'video': return {titulo:lines[0]||'Mira cómo funciona',sub:lines[1]||''};
      case 'popup_social': return {titulo:lines[0]||'🔥 Oferta limitada',cta:lines[1]||'Comprar ahora'};
      default: return {titulo:String(v)};
    }
  }
  function toArr(v, tipo){
    if(Array.isArray(v)) return v;
    if(!v) return [];
    try{ const p=JSON.parse(v); if(Array.isArray(p)) return p; if(typeof p==='object') return [p]; }catch(e){}
    const text = String(v);
    const lines = text.split('\n').filter(l=>l.trim()).map(l=>l.replace(/^[-•*✅\d.)\s]+/,'').trim()).filter(Boolean);
    switch(tipo){
      case 'beneficios': return lines.map(l=>({e:'✅',t:l.substring(0,70),d:l}));
      case 'reviews': return [{stars:5,name:'Cliente verificado',city:'',comment:text}];
      case 'faq':{
        const pairs=[];
        for(let i=0;i<lines.length;i+=2){ if(lines[i]) pairs.push({q:lines[i],a:lines[i+1]||''}); }
        return pairs.length ? pairs : (lines.length?[{q:lines[0],a:text}]:[]);
      }
      default: return lines.map(l=>({text:l}));
    }
  }
  return {
    hero:toObj(c.hero,'hero'), problema:toObj(c.problema,'problema'),
    beneficios:toArr(c.beneficios,'beneficios'), reviews:toArr(c.reviews||c.testimonios,'reviews'),
    faq:toArr(c.faq,'faq'), garantia:toObj(c.garantia,'garantia'),
    cta_final:toObj(c.cta_final,'cta_final'), video:toObj(c.video,'video'),
    popup_social:toObj(c.popup_social||c.popup,'popup_social'),
  };
}

const copy = normalizeCopy(body.copy_editado);
const secSet = new Set(secciones_activas.map(s=>typeof s==='object'?s.id:s));
const hero=copy.hero, prob=copy.problema, bens=copy.beneficios, revs=copy.reviews;
const faqs=copy.faq, ctaF=copy.cta_final, vid=copy.video, pop=copy.popup_social, gar=copy.garantia;
const hT=hero.titulo||NP, hS=hero.sub||'', hB=hero.badge||'';
const hCta=hero.cta||brief.cta_recomendado||'Comprar ahora';
const hPrecio=hero.precio||body.precio||'', hAntes=hero.precio_antes||body.precio_antes||'';
const year=new Date().getFullYear();

// ── CSS POR TEMA ──────────────────────────────────────────────────────────
const fontUrl = `https://fonts.googleapis.com/css2?family=${fuente.replace(/ /g,'+')}:wght@400;600;700;800;900&display=swap`;
const colorRgb = `${pr},${pg},${pb}`;

const CSS_BASE = `
@import url('${fontUrl}');
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--p:${CP}}
body{font-family:'${fuente}',system-ui,sans-serif;background:#fff;color:#111827;line-height:1.6;overflow-x:hidden}
.container{max-width:1100px;margin:0 auto;padding:0 20px}
.sec-label{display:inline-block;background:rgba(${colorRgb},.1);color:${CP};padding:5px 14px;border-radius:50px;font-size:.72rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-bottom:10px}
.sec-h{font-size:clamp(1.6rem,3.5vw,2.4rem);font-weight:800;line-height:1.2;margin-bottom:14px}
.sec-p{font-size:.95rem;color:#6b7280;line-height:1.8;margin-bottom:16px}
.btn{display:inline-block;padding:15px 36px;border-radius:50px;font-weight:800;font-size:1rem;text-decoration:none;cursor:pointer;border:none;font-family:inherit;transition:transform .2s,box-shadow .2s}
.btn:hover{transform:translateY(-2px)}
.btn-p{background:${CP};color:#fff;box-shadow:0 6px 24px rgba(${colorRgb},.35)}
.btn-p:hover{box-shadow:0 10px 32px rgba(${colorRgb},.5)}
.btn-w{background:#fff;color:${CP};border:2px solid ${CP}}
footer{background:#111827;color:rgba(255,255,255,.4);padding:28px;text-align:center;font-size:.82rem}
.pop{position:fixed;top:16px;right:16px;background:#fff;border-radius:10px;padding:10px 14px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-width:220px;z-index:999;border-left:4px solid ${CP};display:none}
.pop.vis{display:block;animation:slideIn .35s ease}
@keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
.pop-t{font-weight:700;font-size:.76rem;color:#111827;margin-bottom:2px}
.pop-c{font-size:.71rem;color:${CP};font-weight:600}
@media(max-width:480px){.pop{max-width:170px;right:8px;top:8px;padding:8px 10px}}
.car-wrap{position:relative;overflow:hidden;border-radius:14px;max-width:520px;margin:32px auto 0;touch-action:pan-y}
.car-slides{display:flex;transition:transform .42s ease;will-change:transform}
.car-slides img{width:100%;aspect-ratio:${ratio_hero};object-fit:cover;display:block;flex-shrink:0}
.car-dots{display:flex;justify-content:center;gap:6px;margin-top:10px}
.car-dot{width:7px;height:7px;border-radius:50%;background:rgba(0,0,0,.22);cursor:pointer;border:none;padding:0;transition:.2s}
.car-dot.on{width:18px;border-radius:4px;background:${CP}}
.car-arr{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.38);color:#fff;border:none;border-radius:50%;width:32px;height:32px;font-size:1rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:3}
.car-arr.p{left:8px}.car-arr.n{right:8px}
/* ── Reviews Estilo 2: Flujo ─── */
.rev-flujo{padding:70px 0;overflow:hidden}
.rev-flujo-hdr{text-align:center;margin-bottom:28px}
.rev-flujo-mask{overflow:hidden;-webkit-mask:linear-gradient(90deg,transparent 0%,#000 10%,#000 90%,transparent 100%);mask:linear-gradient(90deg,transparent 0%,#000 10%,#000 90%,transparent 100%)}
.rev-flujo-track{display:flex;gap:16px;width:max-content;animation:revFlow 36s linear infinite}
.rev-flujo-track:hover{animation-play-state:paused}
@keyframes revFlow{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.rev-flujo-card{background:#f9fafb;border-radius:14px;padding:22px 18px;width:300px;flex-shrink:0;border:1px solid #f3f4f6}
.rev-flujo-stars{color:#f59e0b;font-size:.9rem;margin-bottom:8px}
.rev-flujo-text{font-size:.86rem;color:#374151;line-height:1.65;font-style:italic;margin-bottom:14px}
.rev-flujo-author{display:flex;align-items:center;gap:9px}
.rev-flujo-av{width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0}
.rev-flujo-av-pl{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,${CP},rgba(${colorRgb},.5));display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0}
.rev-flujo-name{font-size:.8rem;font-weight:700;color:#111827}
.rev-flujo-ck{font-size:.71rem;color:#16a34a;font-weight:600}
/* ── Reviews Estilo 3: Carrusel con tap ─── */
.rev-car{padding:70px 0}
.rev-car-card{border-radius:16px;overflow:hidden;background:#fff;border:1px solid #f3f4f6;box-shadow:0 2px 12px rgba(0,0,0,.06);cursor:pointer;transition:box-shadow .2s}
.rev-car-card:hover{box-shadow:0 8px 28px rgba(0,0,0,.1)}
.rev-car-img{width:100%;aspect-ratio:${ratio_revs};object-fit:cover;display:block}
.rev-car-img-ph{width:100%;aspect-ratio:${ratio_revs};background:linear-gradient(135deg,${CP},rgba(${colorRgb},.4));display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.rev-car-foot{padding:14px 16px}
.rev-car-stars{color:#f59e0b;font-size:.9rem;margin-bottom:4px}
.rev-car-name{font-weight:700;font-size:.8rem;color:#111827}
.rev-car-ck{font-size:.7rem;color:#16a34a;font-weight:600;margin-bottom:8px}
.rev-car-body{max-height:0;overflow:hidden;transition:max-height .4s ease,opacity .3s;opacity:0}
.rev-car-card.open .rev-car-body{max-height:300px;opacity:1}
.rev-car-text{font-size:.84rem;color:#374151;line-height:1.65;font-style:italic;padding-top:8px;border-top:1px solid #f3f4f6}
.rev-car-tap{font-size:.72rem;color:${CP};font-weight:600;display:flex;align-items:center;gap:4px;margin-top:6px}
.rev-car-card.open .rev-car-tap{display:none}
/* ── Reviews Estilo 4: Imagen + Leer reseña ─── */
.rev-lee{padding:70px 0}
.rev-lee-card{background:#fff;border-radius:16px;border:1px solid #f3f4f6;box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden}
.rev-lee-img{width:100%;aspect-ratio:${ratio_revs};object-fit:cover;display:block}
.rev-lee-img-ph{width:100%;aspect-ratio:${ratio_revs};background:linear-gradient(135deg,rgba(${colorRgb},.15),rgba(${colorRgb},.05));display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.rev-lee-body{padding:16px 18px}
.rev-lee-stars{color:#f59e0b;font-size:.9rem;margin-bottom:5px}
.rev-lee-name{font-weight:700;font-size:.82rem;color:#111827;margin-bottom:2px}
.rev-lee-ck{font-size:.71rem;color:#16a34a;font-weight:600;margin-bottom:10px}
.rev-lee-btn{display:flex;align-items:center;gap:6px;font-size:.78rem;font-weight:700;color:${CP};cursor:pointer;background:none;border:none;padding:0;font-family:inherit}
.rev-lee-btn svg{transition:transform .3s}
.rev-lee-card.open .rev-lee-btn svg{transform:rotate(180deg)}
.rev-lee-comment{max-height:0;overflow:hidden;transition:max-height .4s ease,opacity .3s;opacity:0}
.rev-lee-card.open .rev-lee-comment{max-height:200px;opacity:1}
.rev-lee-text{font-size:.85rem;color:#374151;line-height:1.7;font-style:italic;padding-top:12px;margin-top:10px;border-top:1px solid #f3f4f6}
/* ── Reviews Estilo 5: Mosaico ─── */
.rev-mosaic{padding:70px 0}
.rev-mosaic-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:32px}
@media(max-width:700px){.rev-mosaic-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:440px){.rev-mosaic-grid{grid-template-columns:1fr}}
.rev-mosaic-card{background:#fff;border-radius:14px;overflow:hidden;border:1px solid #f3f4f6;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:box-shadow .2s}
.rev-mosaic-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.1)}
.rev-mosaic-img{width:100%;aspect-ratio:${ratio_revs};object-fit:cover;display:block}
.rev-mosaic-img-ph{width:100%;aspect-ratio:${ratio_revs};background:linear-gradient(135deg,${CP},rgba(${colorRgb},.25));display:flex;align-items:center;justify-content:center;font-size:2.5rem}
.rev-mosaic-body{padding:14px 16px}
.rev-mosaic-stars{color:#f59e0b;font-size:.88rem;margin-bottom:6px}
.rev-mosaic-text{font-size:.84rem;color:#374151;line-height:1.65;font-style:italic;margin-bottom:12px}
.rev-mosaic-foot{display:flex;align-items:center;gap:9px}
.rev-mosaic-av{width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0}
.rev-mosaic-av-pl{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,${CP},rgba(${colorRgb},.5));display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0}
.rev-mosaic-name{font-weight:700;font-size:.79rem;color:#111827}
.rev-mosaic-ck{font-size:.7rem;color:#16a34a;font-weight:600}
/* ── Reviews: horizontal scroll ─── */
.rev-track-wrap{position:relative;margin-top:28px}
.rev-track{display:flex;gap:16px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:4px 4px 16px;scroll-snap-type:x mandatory}
.rev-track::-webkit-scrollbar{display:none}
.rev-arr{position:absolute;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.96);border:1px solid rgba(0,0,0,.09);border-radius:50%;width:36px;height:36px;font-size:1.15rem;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:3;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:0;transition:.15s;font-family:inherit;line-height:1}
.rev-arr:hover{box-shadow:0 4px 14px rgba(0,0,0,.18)}
.rev-arr.p{left:-8px}.rev-arr.n{right:-8px}
@media(max-width:600px){.rev-arr{display:none}.rev-track{gap:12px;padding:4px 2px 12px}}
/* ─────────────────────────────────────────────── */
.cta-float{position:fixed;bottom:18px;left:50%;transform:translateX(-50%);z-index:890;pointer-events:auto;transition:opacity .3s,transform .3s}
.cta-float.hidden{opacity:0;transform:translateX(-50%) translateY(12px);pointer-events:none}
.cta-float a{display:block;padding:14px 38px;border-radius:50px;background:${CP};color:#fff;font-weight:800;font-size:.95rem;text-decoration:none;box-shadow:0 6px 24px rgba(${colorRgb},.45);white-space:nowrap;transition:box-shadow .2s,transform .2s;font-family:inherit}
.cta-float a:hover{box-shadow:0 10px 32px rgba(${colorRgb},.6);transform:translateY(-2px)}
`;

// Tema 1: Minimal blanco, hero centrado, antes/después, reviews con rating
const CSS_T1 = CSS_BASE + `
.hero{background:linear-gradient(135deg,${CP},rgba(${colorRgb},.65));padding:80px 0 60px;color:#fff}
.hero-badge{display:inline-block;background:rgba(255,255,255,.2);padding:6px 18px;border-radius:50px;font-size:.8rem;font-weight:700;margin-bottom:16px;border:1px solid rgba(255,255,255,.3)}
.hero h1{font-size:clamp(2rem,5vw,3.2rem);font-weight:900;line-height:1.1;margin-bottom:14px}
.hero-sub{font-size:1.05rem;opacity:.9;max-width:500px;margin-bottom:24px}
.hero-price .curr{font-size:2.2rem;font-weight:900}
.hero-price .prev{font-size:1rem;opacity:.6;text-decoration:line-through;margin-left:10px}
.hero-price{margin-bottom:24px}
.hero-trust{display:flex;justify-content:flex-start;gap:20px;margin-top:28px;flex-wrap:wrap;font-size:.8rem;opacity:.85}
.hero-img{display:block;width:100%;border-radius:18px;box-shadow:0 24px 60px rgba(0,0,0,.25)}
.hero-2col{display:grid;grid-template-columns:1fr 1fr;gap:52px;align-items:center}
.hero-img-col{min-width:0}
.hero-img-col .car-wrap{max-width:100%;margin:0;border-radius:18px}
.hero-centered{text-align:center}
.hero-centered .hero-sub{margin:0 auto 24px}
.hero-centered .hero-trust{justify-content:center}
@media(max-width:760px){
  .hero-2col{grid-template-columns:1fr;text-align:center}
  .hero-img-col{order:-1;margin-bottom:22px}
  .hero-text .hero-sub{margin:0 auto 24px;text-align:center}
  .hero-text .hero-trust{justify-content:center}
}
.features{padding:70px 0;background:#f9fafb}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;margin-top:36px}
.feat-card{background:#fff;border-radius:14px;padding:24px 18px;text-align:center;border:1px solid #f3f4f6;transition:.2s}
.feat-card:hover{box-shadow:0 8px 28px rgba(${colorRgb},.1);transform:translateY(-3px)}
.feat-ico{font-size:2rem;margin-bottom:10px}
.feat-card h3{font-size:.95rem;font-weight:700;margin-bottom:6px}
.feat-card p{font-size:.83rem;color:#6b7280;line-height:1.6}
.problema{padding:70px 0}
.problema-inner{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
@media(max-width:700px){.problema-inner{grid-template-columns:1fr}}
.problema img{width:100%;border-radius:14px;box-shadow:0 12px 36px rgba(0,0,0,.1)}
.ab-section{padding:70px 0;background:#f9fafb;text-align:center}
.ab-wrap{display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:780px;margin:32px auto 0}
@media(max-width:580px){.ab-wrap{grid-template-columns:1fr}}
.ab-box{border-radius:14px;overflow:hidden;position:relative}
.ab-label{position:absolute;top:10px;left:10px;background:rgba(0,0,0,.72);color:#fff;padding:4px 10px;border-radius:5px;font-size:.75rem;font-weight:700}
.ab-img{width:100%;display:block;aspect-ratio:4/3;object-fit:cover}
.ab-ph{width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:3rem}
.ab-antes .ab-ph{background:#fef2f2}
.ab-despues .ab-ph{background:#f0fdf4}
.reviews{padding:70px 0}
.rating-hdr{text-align:center;margin-bottom:32px}
.rating-big{font-size:3.2rem;font-weight:900;line-height:1}
.rating-stars{color:#f59e0b;font-size:1.4rem;margin:4px 0}
.rating-cnt{font-size:.82rem;color:#6b7280}
.r-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:18px}
.r-card{background:#f9fafb;border-radius:14px;padding:22px;border:1px solid #f3f4f6}
.r-stars{color:#f59e0b;font-size:.95rem;margin-bottom:8px}
.r-text{font-size:.88rem;color:#374151;line-height:1.7;margin-bottom:14px;font-style:italic}
.r-author{display:flex;align-items:center;gap:10px}
.r-av{width:38px;height:38px;border-radius:50%;object-fit:cover}
.r-av-pl{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,${CP},rgba(${colorRgb},.5));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.85rem}
.r-name{font-weight:700;font-size:.82rem}
.r-ck{font-size:.72rem;color:#16a34a;font-weight:600}
.faq{padding:70px 0;background:#f9fafb}
.faq-list{max-width:740px;margin:32px auto 0;display:flex;flex-direction:column;gap:8px}
.faq-item{background:#fff;border-radius:10px;border:1px solid #f3f4f6;overflow:hidden}
.faq-q{padding:16px 18px;font-weight:600;font-size:.88rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
.faq-q:hover{color:${CP}}
.faq-ic{width:20px;height:20px;background:rgba(${colorRgb},.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:${CP};flex-shrink:0;transition:.2s}
.faq-item.open .faq-ic{transform:rotate(180deg);background:${CP};color:#fff}
.faq-a{padding:0 18px 14px;font-size:.85rem;color:#6b7280;line-height:1.7;display:none}
.faq-item.open .faq-a{display:block}
.garantia{padding:50px 0;background:linear-gradient(135deg,#f0fdf4,#dcfce7);text-align:center}
.gar-ico{font-size:3rem;margin-bottom:10px}
.garantia h2{font-size:1.5rem;font-weight:800;color:#15803d;margin-bottom:8px}
.garantia p{color:#166534;max-width:580px;margin:0 auto}
.cta-f{padding:90px 0;background:linear-gradient(135deg,#111827,#1f2937);text-align:center;color:#fff}
.cta-f h2{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;margin-bottom:14px}
.cta-f p{opacity:.75;max-width:540px;margin:0 auto 28px}
.cta-badges{display:flex;justify-content:center;gap:18px;margin-top:20px;flex-wrap:wrap;font-size:.8rem;opacity:.65}
.escasez{margin-top:14px;font-size:.85rem;color:#f59e0b;font-weight:600}
@media(max-width:680px){.container{padding:0 14px}.hero,.features,.problema,.ab-section,.reviews,.faq,.cta-f{padding:50px 0}}
`;

// Tema 2: Premium, hero 2col, packs, estadísticas, tabla comparativa
const CSS_T2 = CSS_BASE + `
.urgency-bar{background:#ef4444;color:#fff;text-align:center;padding:9px;font-size:.8rem;font-weight:700}
.hero{padding:56px 0;background:#fff}
.hero-inner{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
@media(max-width:760px){.hero-inner{grid-template-columns:1fr;text-align:center}.hero-inner>div:last-child{order:-1;margin-bottom:22px}}
.hero-badge{display:inline-block;background:#fef2f2;color:#ef4444;padding:5px 12px;border-radius:4px;font-size:.72rem;font-weight:700;text-transform:uppercase;margin-bottom:12px;border:1px solid #fecaca}
.hero h1{font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;line-height:1.15;margin-bottom:12px}
.hero-feats{display:flex;flex-direction:column;gap:7px;margin:16px 0 22px}
.hero-feat{display:flex;align-items:center;gap:7px;font-size:.88rem;color:#374151}
.hero-feat b{color:${CP}}
.hero-price .curr{font-size:2.2rem;font-weight:900}
.hero-price .prev{font-size:1rem;color:#9ca3af;text-decoration:line-through;margin-left:8px}
.hero-price .save{display:inline-block;background:#dcfce7;color:#16a34a;padding:2px 9px;border-radius:4px;font-size:.72rem;font-weight:700;margin-left:8px}
.hero-price{margin-bottom:20px}
.hero img{width:100%;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.1)}
.hero-img-ph{background:#f3f4f6;border-radius:10px;aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:4rem}
.packs{padding:28px 0;background:#f9fafb}
.packs h2{text-align:center;font-size:1.05rem;font-weight:800;margin-bottom:14px}
.packs-grid{display:flex;gap:12px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}
.packs-grid::-webkit-scrollbar{display:none}
.pack-card{background:#fff;border-radius:10px;padding:14px 12px;border:2px solid #e5e7eb;text-align:center;position:relative;transition:.2s;min-width:140px;flex:1}
.pack-card:hover,.pack-card.featured{border-color:${CP};box-shadow:0 4px 16px rgba(${colorRgb},.1)}
.pack-badge{position:absolute;top:-8px;left:50%;transform:translateX(-50%);background:${CP};color:#fff;padding:2px 10px;border-radius:50px;font-size:.65rem;font-weight:700;white-space:nowrap}
.pack-qty{font-size:.78rem;font-weight:600;color:#6b7280;margin-bottom:4px}
.pack-price{font-size:1.5rem;font-weight:900;color:#111827;margin-bottom:2px}
.pack-per{font-size:.68rem;color:#9ca3af;margin-bottom:10px}
.ab-section{padding:56px 0}
.ab-inner{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:820px;margin:32px auto 0}
@media(max-width:580px){.ab-inner{grid-template-columns:1fr}}
.ab-box{border-radius:10px;overflow:hidden;position:relative}
.ab-lbl{position:absolute;top:9px;left:9px;padding:3px 9px;border-radius:3px;font-size:.73rem;font-weight:700;letter-spacing:.4px;background:rgba(0,0,0,.7);color:#fff}
.ab-img{width:100%;display:block;aspect-ratio:4/3;object-fit:cover}
.ab-ph{width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:3rem}
.ab-antes .ab-ph{background:#fef2f2}
.ab-despues .ab-ph{background:#f0fdf4}
.testimonials{padding:56px 0;background:#f9fafb}
.test-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;margin-top:32px}
.test-card{background:#fff;border-radius:14px;padding:22px;border:1px solid #f3f4f6}
.test-img{width:100%;height:140px;object-fit:cover;border-radius:8px;margin-bottom:12px}
.test-img-ph{width:100%;height:80px;background:#f3f4f6;border-radius:8px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;font-size:1.8rem}
.test-hl{font-size:.9rem;font-weight:700;margin-bottom:5px}
.test-body{font-size:.82rem;color:#6b7280;line-height:1.6;font-style:italic;margin-bottom:10px}
.test-foot{display:flex;align-items:center;justify-content:space-between}
.test-name{font-size:.76rem;font-weight:600;color:#374151}
.test-stars{color:#f59e0b;font-size:.82rem}
.stats{padding:56px 0}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:32px}
@media(max-width:560px){.stats-grid{grid-template-columns:1fr}}
.stat-box{text-align:center;padding:24px 14px;background:#f9fafb;border-radius:14px}
.stat-num{font-size:2.8rem;font-weight:900;color:${CP};line-height:1}
.stat-lbl{font-size:.82rem;color:#374151;margin-top:6px;line-height:1.5}
.comparativa{padding:56px 0;background:#f9fafb}
.tabla{max-width:680px;margin:32px auto 0;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb}
.tabla-head{display:grid;grid-template-columns:1fr 1fr 1fr;background:#111827;color:#fff;font-weight:700;font-size:.82rem}
.tabla-head div,.tabla-row div{padding:11px 14px;border-right:1px solid rgba(255,255,255,.08)}
.tabla-head div:last-child,.tabla-row div:last-child{border-right:none}
.tabla-row{display:grid;grid-template-columns:1fr 1fr 1fr;border-top:1px solid #f3f4f6;font-size:.82rem;background:#fff}
.tabla-row:nth-child(even){background:#f9fafb}
.check{color:#16a34a;font-weight:700}.cross{color:#ef4444}
.faq{padding:56px 0}
.faq-list{max-width:740px;margin:32px auto 0;display:flex;flex-direction:column;gap:8px}
.faq-item{border:1px solid #e5e7eb;border-radius:9px;overflow:hidden;background:#fff}
.faq-q{padding:15px 18px;font-weight:600;font-size:.86rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
.faq-q:hover{color:${CP}}
.faq-ic{color:#9ca3af;transition:.2s;font-size:.75rem}
.faq-item.open .faq-ic{transform:rotate(180deg);color:${CP}}
.faq-a{padding:0 18px 13px;font-size:.83rem;color:#6b7280;line-height:1.7;display:none}
.faq-item.open .faq-a{display:block}
.garantia{padding:48px 0;background:linear-gradient(135deg,#f0fdf4,#dcfce7);text-align:center}
.gar-ico{font-size:2.8rem;margin-bottom:10px}
.garantia h2{font-size:1.4rem;font-weight:800;color:#15803d;margin-bottom:8px}
.garantia p{color:#166534;max-width:580px;margin:0 auto}
.cta-f{padding:80px 0;background:${CP};text-align:center;color:#fff}
.cta-f h2{font-size:clamp(1.7rem,4vw,2.7rem);font-weight:900;margin-bottom:12px}
.cta-f p{opacity:.9;max-width:540px;margin:0 auto 26px}
.escasez{margin-top:12px;font-size:.82rem;opacity:.8;font-weight:600}
@media(max-width:680px){.container{padding:0 14px}.hero,.ab-section,.testimonials,.stats,.comparativa,.faq,.cta-f{padding:40px 0}.packs{padding:16px 0}}
`;

// Tema 3: Bold, hero oscuro, stats coloreadas, urgencia
const CSS_T3 = CSS_BASE + `
.hero{background:#0f0f1a;padding:80px 0 60px;color:#fff;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:600px;height:500px;background:radial-gradient(circle,rgba(${colorRgb},.18) 0%,transparent 70%);pointer-events:none}
.hero-badge{display:inline-block;background:#ef4444;color:#fff;padding:6px 16px;border-radius:4px;font-size:.76rem;font-weight:800;letter-spacing:.8px;text-transform:uppercase;margin-bottom:18px}
.hero h1{font-size:clamp(2rem,5.5vw,3.6rem);font-weight:900;line-height:1.05;margin-bottom:16px;position:relative}
.hero h1 em{font-style:normal;color:${CP}}
.hero-sub{font-size:1rem;color:rgba(255,255,255,.7);max-width:500px;margin-bottom:24px;line-height:1.8}
.hero-price .curr{font-size:2.2rem;font-weight:900;color:#fff}
.hero-price .prev{font-size:1rem;color:rgba(255,255,255,.4);text-decoration:line-through;margin-left:9px}
.hero-price{margin-bottom:24px}
.hero-trust{display:flex;justify-content:flex-start;gap:20px;margin-top:24px;flex-wrap:wrap;font-size:.78rem;color:rgba(255,255,255,.5)}
.hero-img{display:block;width:100%;border-radius:14px;box-shadow:0 28px 70px rgba(0,0,0,.5);position:relative}
.hero-2col{display:grid;grid-template-columns:1fr 1fr;gap:52px;align-items:center;position:relative;z-index:1}
.hero-img-col{min-width:0}
.hero-img-col .car-wrap{max-width:100%;margin:0;border-radius:14px}
.hero-centered{text-align:center}
.hero-centered .hero-sub{margin:0 auto 24px}
.hero-centered .hero-trust{justify-content:center}
@media(max-width:760px){
  .hero-2col{grid-template-columns:1fr;text-align:center}
  .hero-img-col{order:-1;margin-bottom:22px}
  .hero-text .hero-sub{margin:0 auto 24px;text-align:center}
  .hero-text .hero-trust{justify-content:center}
}
.stats{padding:60px 0;background:#f9fafb}
.stats-note{text-align:center;font-size:.78rem;color:#9ca3af;margin-bottom:32px}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:560px){.stats-grid{grid-template-columns:1fr}}
.stat-card{border-radius:14px;padding:26px 14px;text-align:center}
.stat-card.c1{background:linear-gradient(135deg,#fef2f2,#fee2e2);border:1px solid #fecaca}
.stat-card.c2{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a}
.stat-card.c3{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0}
.stat-num{font-size:3rem;font-weight:900;line-height:1}
.stat-card.c1 .stat-num{color:#dc2626}
.stat-card.c2 .stat-num{color:#d97706}
.stat-card.c3 .stat-num{color:#16a34a}
.stat-lbl{font-size:.8rem;color:#374151;margin-top:7px;line-height:1.5}
.problema{padding:60px 0}
.problema-inner{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
@media(max-width:700px){.problema-inner{grid-template-columns:1fr}}
.problema img{width:100%;border-radius:12px;box-shadow:0 10px 36px rgba(0,0,0,.1)}
.pain-list{display:flex;flex-direction:column;gap:9px;margin-top:14px}
.pain-item{display:flex;align-items:flex-start;gap:9px;background:#fef2f2;padding:11px 13px;border-radius:7px;border-left:3px solid #ef4444;font-size:.84rem;color:#374151;line-height:1.5}
.ab-section{padding:60px 0;background:#f9fafb;text-align:center}
.ab-wrap{display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:780px;margin:32px auto 0}
@media(max-width:580px){.ab-wrap{grid-template-columns:1fr}}
.ab-box{border-radius:12px;overflow:hidden;position:relative}
.ab-lbl{position:absolute;top:9px;left:9px;padding:3px 10px;border-radius:4px;font-size:.73rem;font-weight:800}
.ab-antes .ab-lbl{background:#ef4444;color:#fff}
.ab-despues .ab-lbl{background:#16a34a;color:#fff}
.ab-img{width:100%;display:block;aspect-ratio:4/3;object-fit:cover}
.ab-ph{width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:3rem}
.ab-antes .ab-ph{background:#fef2f2}
.ab-despues .ab-ph{background:#f0fdf4}
.reviews{padding:60px 0}
.reviews-hdr{text-align:center;margin-bottom:32px}
.r-big-stars{color:#f59e0b;font-size:1.8rem}
.r-big-score{font-size:1rem;font-weight:700;color:#111827;margin-top:4px}
.r-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px}
.r-card{background:#f9fafb;border-radius:12px;padding:20px;border-left:4px solid ${CP}}
.r-stars{color:#f59e0b;font-size:.9rem;margin-bottom:7px}
.r-text{font-size:.86rem;color:#374151;line-height:1.7;margin-bottom:12px;font-style:italic}
.r-author{display:flex;align-items:center;gap:8px}
.r-av{width:36px;height:36px;border-radius:50%;object-fit:cover}
.r-av-pl{width:36px;height:36px;border-radius:50%;background:${CP};display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.83rem}
.r-name{font-weight:700;font-size:.8rem}
.r-tick{font-size:.7rem;color:#16a34a;font-weight:600}
.faq{padding:60px 0;background:#f9fafb}
.faq-list{max-width:740px;margin:32px auto 0;display:flex;flex-direction:column;gap:8px}
.faq-item{background:#fff;border-radius:9px;border:1px solid #e5e7eb;overflow:hidden}
.faq-q{padding:15px 18px;font-weight:600;font-size:.86rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
.faq-q:hover{color:${CP}}
.faq-ic{color:#9ca3af;transition:.2s;font-size:.75rem}
.faq-item.open .faq-ic{transform:rotate(180deg);color:${CP}}
.faq-a{padding:0 18px 13px;font-size:.83rem;color:#6b7280;line-height:1.7;display:none}
.faq-item.open .faq-a{display:block}
.garantia{padding:48px 0;background:linear-gradient(135deg,#f0fdf4,#dcfce7);text-align:center}
.gar-ico{font-size:2.8rem;margin-bottom:10px}
.garantia h2{font-size:1.4rem;font-weight:800;color:#15803d;margin-bottom:8px}
.garantia p{color:#166534;max-width:580px;margin:0 auto}
.cta-f{padding:80px 0;background:#0f0f1a;text-align:center;color:#fff;position:relative;overflow:hidden}
.cta-f::before{content:'';position:absolute;bottom:-60px;left:50%;transform:translateX(-50%);width:500px;height:280px;background:radial-gradient(circle,rgba(${colorRgb},.2) 0%,transparent 70%);pointer-events:none}
.cta-urgencia{display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:7px 16px;border-radius:5px;font-size:.76rem;font-weight:700;margin-bottom:24px;text-transform:uppercase;letter-spacing:.5px}
.cta-f h2{font-size:clamp(1.8rem,4.5vw,2.9rem);font-weight:900;line-height:1.1;margin-bottom:14px;position:relative}
.cta-f p{opacity:.7;max-width:500px;margin:0 auto 26px;font-size:.98rem;position:relative}
.escasez{margin-top:14px;font-size:.8rem;color:rgba(255,255,255,.45)}
@media(max-width:680px){.container{padding:0 14px}.hero,.stats,.problema,.ab-section,.reviews,.faq,.cta-f{padding:44px 0}}
`;

// ── SELECCIONAR CSS ───────────────────────────────────────────────────────
const css = tema===2 ? CSS_T2 : tema===3 ? CSS_T3 : CSS_T1;

// ── CONSTRUIR SECCIONES ───────────────────────────────────────────────────
let secs = '';
let popScript = '';

// HERO
function buildCarousel(imgs, alt, ratio){
  const ar = ratio||'4/3';
  if(imgs.length === 1) return `<div class="hero-img" style="aspect-ratio:${ar};overflow:hidden"><img src="${esc(imgs[0])}" alt="${esc(alt)}" style="width:100%;height:100%;object-fit:cover;display:block"></div>`;
  const slides = imgs.map(u=>`<img src="${esc(u)}" alt="${esc(alt)}" style="aspect-ratio:${ar};object-fit:cover;width:100%;flex-shrink:0;display:block">`).join('');
  const dots = imgs.map((_,i)=>`<button class="car-dot${i===0?' on':''}" data-i="${i}"></button>`).join('');
  return `<div class="car-wrap" id="hCar"><button class="car-arr p" id="hPrev">&#8249;</button><div class="car-slides" id="hSlides">${slides}</div><button class="car-arr n" id="hNext">&#8250;</button></div><div class="car-dots" id="hDots">${dots}</div>`;
}
const heroImgs = fotos_hero.length ? (hero_estilo==='B' ? fotos_hero : [fotos_hero[0]]) : [];
const heroImgTag = heroImgs.length ? buildCarousel(heroImgs, hT, ratio_hero) : '';
const precioTag = hPrecio ? `<div class="hero-price"><span class="curr">${esc(hPrecio)}</span>${hAntes?`<span class="prev">${esc(hAntes)}</span>`:''}</div>` : '';
const trustTag = `<div class="hero-trust">✔ Envío gratis &nbsp;·&nbsp; ✔ Pago al recibir &nbsp;·&nbsp; ✔ Garantía 100%</div>`;

if (tema === 2) {
  let imgColContent = '';
  if(fotos_hero[0]) {
    const t2Imgs = hero_estilo==='B' ? fotos_hero : [fotos_hero[0]];
    imgColContent = t2Imgs.length>1 ? buildCarousel(t2Imgs, hT, ratio_hero).replace('class="car-wrap"','class="car-wrap" style="margin:0;max-width:100%;border-radius:10px"') : `<div style="aspect-ratio:${ratio_hero};overflow:hidden;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.1)"><img src="${esc(fotos_hero[0])}" alt="${esc(hT)}" style="width:100%;height:100%;object-fit:cover;display:block"></div>`;
  } else { imgColContent = `<div class="hero-img-ph">📸</div>`; }
  const imgCol = `<div>${imgColContent}</div>`;
  secs += `<div class="urgency-bar">⚡ ¡ÚLTIMAS UNIDADES! Oferta por tiempo limitado</div>
<section class="hero"><div class="container"><div class="hero-inner"><div>
${hB?`<div class="hero-badge">${esc(hB)}</div>`:''}
<h1>${esc(hT)}</h1>
<div class="hero-feats">
  <div class="hero-feat"><b>✓</b> Resultados visibles desde el primer uso</div>
  <div class="hero-feat"><b>✓</b> Envío gratis a todo el país</div>
  <div class="hero-feat"><b>✓</b> Pago al recibir disponible</div>
</div>
${hS?`<p class="sec-p">${esc(hS)}</p>`:''}
${precioTag}
<a href="#cta" class="btn btn-p">${esc(hCta)}</a>
</div>${imgCol}</div></div></section>`;
} else {
  const badgeTag = hB ? `<div class="hero-badge">${esc(hB)}</div>` : (tema===3 ? '<div class="hero-badge">🔥 ÚLTIMAS UNIDADES</div>' : '');
  const h1Content = tema===3 ? `<em>${esc(hT.split(' ').slice(0,2).join(' '))}</em> ${esc(hT.split(' ').slice(2).join(' '))}` : esc(hT);
  if(heroImgTag){
    secs += `<section class="hero"><div class="container"><div class="hero-2col">
<div class="hero-text">
${badgeTag}<h1>${h1Content}</h1>
${hS?`<p class="hero-sub">${esc(hS)}</p>`:''}
${precioTag}
<a href="#cta" class="btn btn-p">${esc(hCta)}</a>
${trustTag}
</div>
<div class="hero-img-col">${heroImgTag}</div>
</div></div></section>`;
  } else {
    secs += `<section class="hero hero-centered"><div class="container">
${badgeTag}<h1>${h1Content}</h1>
${hS?`<p class="hero-sub">${esc(hS)}</p>`:''}
${precioTag}
<a href="#cta" class="btn btn-p">${esc(hCta)}</a>
${trustTag}
</div></section>`;
  }
}

// STATS (Tema 2 y 3)
if (tema === 2 || tema === 3) {
  const statData = [
    {n:'94%', l:'de usuarios reportaron resultados visibles desde el primer uso', c:'c1'},
    {n:'89%', l:'de clientes repiten la compra y la recomiendan a conocidos', c:'c2'},
    {n:'97%', l:'valoró el producto como cómodo y fácil de usar diariamente', c:'c3'}
  ];
  const stH = statData.map(s => {
    const cls = tema===3 ? `stat-card ${s.c}` : 'stat-box';
    const numCls = tema===3 ? 'stat-num' : 'stat-num';
    const lblCls = tema===3 ? 'stat-lbl' : 'stat-lbl';
    return `<div class="${cls}"><div class="${numCls}">${s.n}</div><div class="${lblCls}">${s.l}</div></div>`;
  }).join('');
  secs += `<section class="stats"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">Resultados comprobados</h2>
<p class="stats-note">*Datos obtenidos de encuestas a compradores verificados</p>
<div class="stats-grid">${stH}</div></div></section>`;
}

// PACKS (Tema 2)
if (tema === 2) {
  const packs = [
    {qty:'1 unidad', price: hPrecio||'', badge:null, featured:false, per:'Precio unitario'},
    {qty:'2 unidades', price:'', badge:'⭐ Más vendido', featured:true, per:'El más elegido'},
    {qty:'3 unidades', price:'', badge:'💰 Mejor oferta', featured:false, per:'Máximo ahorro'}
  ];
  const pH = packs.map((p,i) => `<div class="pack-card${p.featured?' featured':''}">
${p.badge?`<div class="pack-badge">${p.badge}</div>`:''}
<div class="pack-qty">${p.qty}</div>
<div class="pack-price">${i===0&&p.price?esc(p.price):'Consultar'}</div>
<div class="pack-per">${p.per}</div>
<a href="#cta" class="btn btn-p" style="width:100%;display:block;border-radius:8px;text-align:center">${esc(hCta)}</a>
</div>`).join('');
  secs += `<section class="packs"><div class="container"><h2>Elegí tu pack</h2><div class="packs-grid">${pH}</div></div></section>`;
}

// PROBLEMA
if (secSet.has('problema') && prob.titulo) {
  const pImgH = foto_problema ? `<div><img src="${esc(foto_problema)}" alt="problema" style="width:100%;aspect-ratio:${ratio_prob};object-fit:cover;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,.1)"></div>` : '';
  if (tema === 3) {
    const painItems = prob.desc ? prob.desc.split('.').filter(s=>s.trim().length>8).slice(0,3).map(s=>`<div class="pain-item">— ${esc(s.trim())}.</div>`).join('') : '';
    secs += `<section class="problema"><div class="container"><div class="problema-inner"><div>
<h2 class="sec-h">${esc(prob.titulo)}</h2>
${prob.desc?`<p class="sec-p">${esc(prob.desc)}</p>`:''}
${painItems?`<div class="pain-list">${painItems}</div>`:''}
</div>${pImgH}</div></div></section>`;
  } else {
    secs += `<section class="problema"><div class="container"><div class="problema-inner"><div>
<h2 class="sec-h">${esc(prob.titulo)}</h2>
${prob.desc?`<p class="sec-p">${esc(prob.desc)}</p>`:''}
</div>${pImgH}</div></div></section>`;
  }
}

// ANTES/DESPUÉS
const antesImg = foto_antes   ? `<img src="${esc(foto_antes)}"   alt="Antes"    class="ab-img" style="aspect-ratio:${ratio_antes};object-fit:cover">` : `<div class="ab-ph" style="aspect-ratio:${ratio_antes}">😤</div>`;
const despImg  = foto_despues ? `<img src="${esc(foto_despues)}" alt="Después"  class="ab-img" style="aspect-ratio:${ratio_despues};object-fit:cover">` : `<div class="ab-ph" style="aspect-ratio:${ratio_despues}">✨</div>`;
const abWrapClass = tema===2 ? 'ab-inner' : 'ab-wrap';
secs += `<section class="ab-section"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 24px;max-width:700px">Antes vs. Después</h2>
<div class="${abWrapClass}">
  <div class="ab-box ab-antes"><div class="ab-lbl">ANTES</div>${antesImg}</div>
  <div class="ab-box ab-despues"><div class="ab-lbl">DESPUÉS</div>${despImg}</div>
</div></div></section>`;

// BENEFICIOS
if (secSet.has('beneficios') && bens.length) {
  const bH = bens.map(b => `<div class="feat-card">
<div class="feat-ico">${b.e||'✅'}</div>
<h3>${esc(b.t||'')}</h3>
<p>${esc(b.d||'')}</p>
</div>`).join('');
  secs += `<section class="features" style="padding:60px 0${tema===2||tema===3?';background:#fff':''}"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 32px;max-width:700px">¿Por qué elegir ${esc(NP)}?</h2>
<div class="features-grid">${bH}</div></div></section>`;
}

// VIDEO
if (secSet.has('video') && vid.titulo) {
  const vUrl = toEmbedUrl(body.video_url||'');
  const vE = vUrl
    ? `<div style="max-width:820px;margin:0 auto;border-radius:18px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.5)"><iframe src="${esc(vUrl)}" style="width:100%;aspect-ratio:16/9;border:none;display:block" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>`
    : `<div style="max-width:820px;margin:0 auto;background:#111;aspect-ratio:16/9;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:4rem;opacity:.6">▶️</div>`;
  secs += `<section style="padding:70px 0;text-align:center;background:#0a0a0f"><div class="container">
<h2 style="text-align:center;margin:0 auto 14px;max-width:700px;font-size:clamp(1.5rem,3vw,2.2rem);font-weight:800;color:#fff">${esc(vid.titulo)}</h2>
${vid.sub?`<p style="text-align:center;margin:0 auto 32px;max-width:600px;color:rgba(255,255,255,.6);font-size:.95rem;line-height:1.7">${esc(vid.sub)}</p>`:'<div style="margin-bottom:32px"></div>'}
${vE}</div></section>`;
}

// REVIEWS
if (secSet.has('reviews') && revs.length) {

  if (reviews_estilo === 2) {
    // ── FLUJO: carrusel horizontal infinito ──
    const cards = revs.map((r,i) => {
      const av = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="rev-flujo-av" alt="">` : `<div class="rev-flujo-av-pl">${(r.name||'C').charAt(0).toUpperCase()}</div>`;
      return `<div class="rev-flujo-card"><div class="rev-flujo-stars">${stars(r.stars)}</div><p class="rev-flujo-text">"${esc(r.comment||'')}"</p><div class="rev-flujo-author">${av}<div><div class="rev-flujo-name">${esc(r.name||'Cliente verificado')}${r.city?' · '+esc(r.city):''}</div><div class="rev-flujo-ck">✓ Compra verificada</div></div></div></div>`;
    }).join('');
    secs += `<section class="rev-flujo"><div class="container rev-flujo-hdr">
<h2 class="sec-h" style="margin:0 auto 0;max-width:700px">Lo que dicen nuestros clientes</h2>
<p class="sec-p" style="margin:8px auto 0;max-width:560px">+3.000 clientes satisfechos en toda la región</p>
</div>
<div class="rev-flujo-mask"><div class="rev-flujo-track">${cards+cards}</div></div></section>`;

  } else if (reviews_estilo === 3) {
    // ── CARRUSEL TAP: imagen + stars + nombre, tap expande comentario ──
    const cards = revs.map((r,i) => {
      const img = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="rev-car-img" alt="">` : `<div class="rev-car-img-ph">👤</div>`;
      return `<div class="rev-car-card" style="width:200px;flex-shrink:0;scroll-snap-align:start" onclick="this.classList.toggle('open')">
${img}
<div class="rev-car-foot">
<div class="rev-car-stars">${stars(r.stars)}</div>
<div class="rev-car-name">${esc(r.name||'Cliente verificado')}</div>
<div class="rev-car-ck">✓ Compra verificada${r.city?' · '+esc(r.city):''}</div>
<div class="rev-car-tap">▼ Leer reseña</div>
<div class="rev-car-body"><p class="rev-car-text">"${esc(r.comment||'')}"</p></div>
</div></div>`;
    }).join('');
    secs += `<section class="rev-car"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">Lo que dicen nuestros clientes</h2>
<p class="sec-p" style="text-align:center;margin:0 auto 16px;max-width:560px">+3.000 clientes satisfechos · Toca para leer la reseña completa</p>
<div class="rev-track-wrap"><button class="rev-arr p" onclick="revScroll('rT3',-1)">&#8249;</button><div class="rev-track" id="rT3">${cards}</div><button class="rev-arr n" onclick="revScroll('rT3',1)">&#8250;</button></div></div></section>`;

  } else if (reviews_estilo === 4) {
    // ── IMAGEN + LEER RESEÑA: imagen visible, comentario se abre al tap ──
    const cards = revs.map((r,i) => {
      const img = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="rev-lee-img" alt="">` : `<div class="rev-lee-img-ph">👤</div>`;
      return `<div class="rev-lee-card" style="width:220px;flex-shrink:0;scroll-snap-align:start">
${img}
<div class="rev-lee-body">
<div class="rev-lee-stars">${stars(r.stars)}</div>
<div class="rev-lee-name">${esc(r.name||'Cliente verificado')}</div>
<div class="rev-lee-ck">✓ Compra verificada${r.city?' · '+esc(r.city):''}</div>
<button class="rev-lee-btn" onclick="this.closest('.rev-lee-card').classList.toggle('open')">
Leer reseña <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
</button>
<div class="rev-lee-comment"><p class="rev-lee-text">"${esc(r.comment||'')}"</p></div>
</div></div>`;
    }).join('');
    secs += `<section class="rev-lee"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">Lo que opinan nuestros clientes</h2>
<p class="sec-p" style="text-align:center;margin:0 auto 16px;max-width:560px">+3.000 clientes satisfechos en toda la región</p>
<div class="rev-track-wrap"><button class="rev-arr p" onclick="revScroll('rT4',-1)">&#8249;</button><div class="rev-track" id="rT4">${cards}</div><button class="rev-arr n" onclick="revScroll('rT4',1)">&#8250;</button></div></div></section>`;

  } else if (reviews_estilo === 5) {
    // ── MOSAICO: grid con imagen + texto completo ──
    const cards = revs.map((r,i) => {
      const img = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="rev-mosaic-img" alt="">` : `<div class="rev-mosaic-img-ph">👤</div>`;
      const av = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="rev-mosaic-av" alt="">` : `<div class="rev-mosaic-av-pl">${(r.name||'C').charAt(0).toUpperCase()}</div>`;
      return `<div class="rev-mosaic-card">${img}<div class="rev-mosaic-body"><div class="rev-mosaic-stars">${stars(r.stars)}</div><p class="rev-mosaic-text">"${esc(r.comment||'')}"</p><div class="rev-mosaic-foot">${av}<div><div class="rev-mosaic-name">${esc(r.name||'Cliente verificado')}</div><div class="rev-mosaic-ck">✓ Compra verificada${r.city?' · '+esc(r.city):''}</div></div></div></div></div>`;
    }).join('');
    const mHdr = tema===2
      ? `<h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">Lo que dicen nuestros clientes</h2><p class="sec-p" style="text-align:center;margin:0 auto 28px">+3.000 clientes satisfechos en toda la región</p>`
      : tema===3
      ? `<div class="reviews-hdr"><div class="r-big-stars">★★★★★</div><div class="r-big-score">4.8/5 · Basado en +2.000 valoraciones verificadas</div></div>`
      : `<div class="rating-hdr"><div class="rating-big">4.8</div><div class="rating-stars">★★★★★</div><div class="rating-cnt">Basado en +2.000 valoraciones verificadas</div></div>`;
    secs += `<section class="rev-mosaic"><div class="container">${mHdr}<div class="rev-mosaic-grid">${cards}</div></div></section>`;

  } else {
    // ── CLÁSICO: horizontal scroll ──
    const rH = revs.map((r, i) => {
      const av = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="r-av" alt="${esc(r.name||'')}">` : `<div class="r-av-pl">${(r.name||'C').charAt(0).toUpperCase()}</div>`;
      if (tema === 2) {
        const rImg = fotos_reviews[i] ? `<img src="${esc(fotos_reviews[i])}" class="test-img" alt="">` : `<div class="test-img-ph">😊</div>`;
        return `<div class="test-card" style="width:260px;flex-shrink:0;scroll-snap-align:start">${rImg}<div class="test-hl">"Excelente producto"</div><div class="test-body">${esc(r.comment||'')}</div><div class="test-foot"><div class="test-name">${esc(r.name||'Cliente')}${r.city?' · '+esc(r.city):''}</div><div class="test-stars">${stars(r.stars)}</div></div></div>`;
      }
      return `<div class="r-card" style="width:270px;flex-shrink:0;scroll-snap-align:start"><div class="r-stars">${stars(r.stars)}</div><p class="r-text">"${esc(r.comment||'')}"</p><div class="r-author">${av}<div><div class="r-name">${esc(r.name||'Cliente verificado')}${r.city?' · '+esc(r.city):''}</div><div class="${tema===3?'r-tick':'r-ck'}">✓ Compra verificada</div></div></div></div>`;
    }).join('');
    if (tema === 2) {
      secs += `<section class="testimonials"><div class="container"><h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">Lo que dicen nuestros clientes</h2><p class="sec-p" style="text-align:center;margin:0 auto 28px">+3.000 clientes satisfechos en toda la región</p><div class="rev-track-wrap"><button class="rev-arr p" onclick="revScroll('rT1',-1)">&#8249;</button><div class="rev-track" id="rT1">${rH}</div><button class="rev-arr n" onclick="revScroll('rT1',1)">&#8250;</button></div></div></section>`;
    } else if (tema === 3) {
      secs += `<section class="reviews"><div class="container"><div class="reviews-hdr"><div class="r-big-stars">★★★★★</div><div class="r-big-score">4.8/5 · Basado en +2.000 valoraciones verificadas</div></div><div class="rev-track-wrap"><button class="rev-arr p" onclick="revScroll('rT1',-1)">&#8249;</button><div class="rev-track" id="rT1">${rH}</div><button class="rev-arr n" onclick="revScroll('rT1',1)">&#8250;</button></div></div></section>`;
    } else {
      secs += `<section class="reviews"><div class="container"><div class="rating-hdr"><div class="rating-big">4.8</div><div class="rating-stars">★★★★★</div><div class="rating-cnt">Basado en +2.000 valoraciones verificadas</div></div><div class="rev-track-wrap"><button class="rev-arr p" onclick="revScroll('rT1',-1)">&#8249;</button><div class="rev-track" id="rT1">${rH}</div><button class="rev-arr n" onclick="revScroll('rT1',1)">&#8250;</button></div></div></section>`;
    }
  }
}

// TABLA COMPARATIVA (solo Tema 2)
if (tema === 2) {
  secs += `<section class="comparativa"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 8px;max-width:700px">${esc(NP)} vs. la competencia</h2>
<div class="tabla">
  <div class="tabla-head"><div>${esc(NP)}</div><div>Competencia</div><div>Otros</div></div>
  <div class="tabla-row"><div>Resultados inmediatos</div><div class="check">✓</div><div class="cross">✗</div></div>
  <div class="tabla-row"><div>Pago al recibir</div><div class="check">✓</div><div class="cross">✗</div></div>
  <div class="tabla-row"><div>Garantía incluida</div><div class="check">✓</div><div class="cross">✗</div></div>
  <div class="tabla-row"><div>Envío gratis</div><div class="check">✓</div><div class="check">✓</div></div>
  <div class="tabla-row"><div>Soporte postventa</div><div class="check">✓</div><div class="cross">✗</div></div>
</div></div></section>`;
}

// FAQ
if (secSet.has('faq') && faqs.length) {
  const fH = faqs.map((f,i) => `<div class="faq-item" id="fi${i}">
<div class="faq-q" onclick="tF(${i})"><span>${esc(f.q||'')}</span><span class="faq-ic">▼</span></div>
<div class="faq-a">${esc(f.a||'')}</div>
</div>`).join('');
  secs += `<section class="faq"><div class="container">
<h2 class="sec-h" style="text-align:center;margin:0 auto 0;max-width:700px">Preguntas frecuentes</h2>
<div class="faq-list">${fH}</div></div></section>`;
}

// GARANTÍA
if (secSet.has('garantia')) {
  secs += `<section class="garantia"><div class="container">
<div class="gar-ico">🛡️</div>
<h2>${esc(gar.titulo||'100% Garantizado')}</h2>
${gar.desc?`<p>${esc(gar.desc)}</p>`:''}
</div></section>`;
}

// CTA FINAL
const ctaFT = esc(ctaF.titulo||hT), ctaFS = esc(ctaF.sub||''), ctaFB = esc(ctaF.btn||hCta), ctaFE = ctaF.escasez||'';
if (tema === 3) {
  secs += `<section class="cta-f" id="cta"><div class="container" style="position:relative;z-index:2;max-width:680px;margin:0 auto;text-align:center">
<div class="cta-urgencia">⚡ ${ctaFE?esc(ctaFE):'ÚLTIMAS UNIDADES DISPONIBLES'}</div>
<h2>${ctaFT}</h2>
${ctaFS?`<p>${ctaFS}</p>`:''}
<a href="#" class="btn btn-p">${ctaFB}</a>
<p class="escasez">✔ Envío gratis · ✔ Pago al recibir · ✔ Garantía 100%</p>
</div></section>`;
} else {
  secs += `<section class="cta-f" id="cta"><div class="container">
<h2>${ctaFT}</h2>
${ctaFS?`<p>${ctaFS}</p>`:''}
<a href="#" class="btn ${tema===2?'btn-w':'btn-p'}">${ctaFB}</a>
${ctaFE?`<p class="escasez">⚡ ${esc(ctaFE)}</p>`:''}
<div class="cta-badges" style="display:flex;justify-content:center;gap:18px;margin-top:18px;flex-wrap:wrap;font-size:.8rem;opacity:.65">
  <span>✔ Envío gratis</span><span>✔ Pago al recibir</span><span>✔ Garantía incluida</span>
</div>
</div></section>`;
}

// POPUP
if (secSet.has('popup_social')) {
  const pT2 = esc(pop.titulo||'🔥 Oferta limitada'), pC = esc(pop.cta||hCta);
  secs += `<div class="pop" id="pop"><div class="pop-t">${pT2}</div><div class="pop-c">${pC}</div></div>`;
  popScript = `setTimeout(function(){var p=document.getElementById('pop');if(p)p.classList.add('vis');},4000);`;
}

// ── SCRIPT FAQ ────────────────────────────────────────────────────────────
const faqScript = faqs.length ? `function tF(i){var el=document.getElementById('fi'+i);if(el)el.classList.toggle('open');}` : 'function tF(){}';
// ── SCRIPT REVIEWS SCROLL ─────────────────────────────────────────────────
const revScrollScript = `function revScroll(id,dir){var el=document.getElementById(id);if(el)el.scrollBy({left:dir*300,behavior:'smooth'});}`;


// ── SCRIPT CARRUSEL ───────────────────────────────────────────────────────
const carScript = (heroImgs.length > 1) ? `(function(){
var wrap=document.getElementById('hCar');if(!wrap)return;
var sl=document.getElementById('hSlides');
var dots=document.querySelectorAll('#hDots .car-dot');
var total=dots.length;var cur=0;
function go(n){cur=(n+total)%total;sl.style.transform='translateX(-'+(cur*100)+'%)';dots.forEach(function(d,i){d.classList.toggle('on',i===cur);});}
document.getElementById('hPrev').onclick=function(){go(cur-1);};
document.getElementById('hNext').onclick=function(){go(cur+1);};
dots.forEach(function(d){d.addEventListener('click',function(){go(Number(d.dataset.i));});});
var sx=0;
wrap.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;},{passive:true});
wrap.addEventListener('touchend',function(e){var dx=e.changedTouches[0].clientX-sx;if(dx<-40)go(cur+1);else if(dx>40)go(cur-1);},{passive:true});
setInterval(function(){go(cur+1);},4500);
})();` : '';

// ── SCRIPT CTA FLOTANTE ───────────────────────────────────────────────────
const ctaFloatBtn = esc(ctaF.btn||hCta);
const ctaScript = `(function(){
var fb=document.getElementById('ctaFloat');if(!fb)return;
var btn=fb.querySelector('a');
var labels=[
  {sel:'.hero',     txt:'${ctaFloatBtn}'},
  {sel:'.problema', txt:'Quiero solucionar esto'},
  {sel:'.ab-section',txt:'Quiero estos resultados'},
  {sel:'.features', txt:'Quiero todos los beneficios'},
  {sel:'.reviews',  txt:'Comprar como ellos'},
  {sel:'.faq',      txt:'Sí, lo quiero'},
  {sel:'#cta',      txt:'__hide__'}
];
function update(){
  var found=null;
  labels.forEach(function(l){var el=document.querySelector(l.sel);if(!el)return;var r=el.getBoundingClientRect();if(r.top<=window.innerHeight/2&&r.bottom>=window.innerHeight/2)found=l;});
  if(found){if(found.txt==='__hide__'){fb.classList.add('hidden');}else{fb.classList.remove('hidden');btn.textContent=found.txt;}}
  else{fb.classList.remove('hidden');btn.textContent='${ctaFloatBtn}';}
}
var io=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.target.id==='cta'){fb.classList.toggle('hidden',e.isIntersecting);}});},{threshold:0.3});
var ctaEl=document.getElementById('cta');if(ctaEl)io.observe(ctaEl);
window.addEventListener('scroll',update,{passive:true});
setTimeout(update,400);
})();`;

// ── HTML FINAL ────────────────────────────────────────────────────────────
const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>${NP}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<style>${css}</style>
</head>
<body>
${secs}
<div class="cta-float hidden" id="ctaFloat"><a href="#cta">${ctaFloatBtn}</a></div>
<footer><p>© ${year} ${NP}. Todos los derechos reservados.</p></footer>
<script>${faqScript}${popScript}${revScrollScript}${carScript}${ctaScript}</script>
</body>
</html>`;

return [{ json: { success: true, html, tipo_salida, tema } }];
