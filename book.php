<?php require 'inc.php';
/*
 * Book of Abstracts dalam bentuk flipbook. PDF dirender di browser dengan PDF.js (cdnjs),
 * halaman dibalik dengan animasi 3D (dua halaman di layar lebar, satu halaman di ponsel).
 */
$u = user(); $L = lang(); $H = BASE; $bf = bookFile();
$preview = !bookOn() && $bf && $u && $u['role'] === 'admin';
if (!bookOn() && !$preview) { http_response_code(404); head(t('Book of Abstracts')); ?><h2><?= te('Book of Abstracts') ?></h2><p class="panel"><?= te('The book of abstracts is not available yet. Please check back later.') ?></p><p><a class="btn" href="<?= $H ?>"><?= te('Back to home') ?></a></p><?php foot(); exit; }
$pdf = $H . $bf;
$title = setting('book_title') ?: 'Book of Abstracts & Conference Programme';
$zoom = ''; $zoomHint = false;
if (setting('book_zoom', '1') === '1' && isurl(setting('online_link'))) {
    if ($u && $u['role'] === 'admin') $zoom = setting('online_link');
    elseif ($u && $u['role'] === 'peserta' && ($R = regOf($u['id'])) && $R['pay_status'] === 'verified' && $R['attendance'] === 'Online') $zoom = setting('online_link');
    else $zoomHint = true;
}
$xl = isurl(setting('book_link_url')) ? setting('book_link_url') : ''; $xlLabel = tx(setting('book_link_label') ?: 'Link'); $req = $xl && setting('book_link_req') === '1';
$short = function ($url) { return preg_replace('#^https?://(www\.)?#i', '', rtrim($url, '/')); };
$J = ['pdf' => $pdf, 'page' => t('Page'), 'of' => t('of'), 'copied' => t('Link copied'), 'fail' => t('The book could not be loaded.'), 'loading' => t('Loading book…')];
?><!DOCTYPE html><html lang="<?= $L ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(tx($title)) ?> - <?= e(brand()) ?></title>
<meta name="description" content="<?= e(tx($title) . ' · ' . setting('name')) ?>"><meta name="theme-color" content="#16302f">
<meta property="og:title" content="<?= e(tx($title) . ' · ' . brand()) ?>"><meta property="og:type" content="book"><meta property="og:url" content="<?= e(absUrl('book.php')) ?>">
<?php if (setting('poster')): ?><meta property="og:image" content="<?= e(absUrl(imgSrc(setting('poster')))) ?>"><?php endif; ?>
<?php if (setting('logo_url')): ?><link rel="icon" href="<?= e(imgSrc(setting('logo_url'))) ?>"><?php endif; ?>
<style>
:root{--ground:#16302f;--ground2:#1d3a3a;--gold:#d4a373;--gold-hi:#f1d6b0;--bone:#f8f4ec;--muted:#a9bdb9;--paper:#fffdf8;
--display:Georgia,"Times New Roman",serif;--sans:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
*{box-sizing:border-box}html,body{height:100%}
body{margin:0;color:var(--bone);font-family:var(--sans);overflow:hidden;background:radial-gradient(120% 80% at 50% -10%,#2a5250 0%,transparent 60%),radial-gradient(100% 60% at 50% 110%,#0f2322 0%,transparent 64%),var(--ground)}
a{color:inherit}
.app{height:100%;display:flex;flex-direction:column;min-height:0}
/* ===== sampul / gerbang ===== */
.gate{position:fixed;inset:0;z-index:40;display:grid;place-items:center;padding:24px 16px calc(24px + env(safe-area-inset-bottom,0px));background:radial-gradient(115% 78% at 50% -4%,#2c5654 0%,transparent 62%),var(--ground);transition:opacity .9s ease,visibility .9s;overflow:auto}
.gate.gone{opacity:0;visibility:hidden;pointer-events:none}
.card{position:relative;width:min(980px,100%);max-height:100%;overflow:auto;padding:clamp(28px,4vw,44px) clamp(22px,4vw,48px);border:2px solid rgba(212,163,115,.85);background:linear-gradient(168deg,#25504e 0%,#1d3a3a 55%,#16302f 100%);box-shadow:0 40px 90px -32px rgba(0,0,0,.85)}
.card::before{content:"";position:absolute;inset:8px;border:1px solid rgba(212,163,115,.34);pointer-events:none}
.grid{display:grid;grid-template-columns:minmax(0,.78fr) minmax(0,1fr);gap:clamp(26px,4vw,48px);align-items:center}
.cover{justify-self:center;width:100%;max-width:300px;aspect-ratio:1/1.414;border:1px solid rgba(212,163,115,.9);background:rgba(255,255,255,.04);box-shadow:0 22px 46px -18px rgba(0,0,0,.9),0 0 0 7px rgba(212,163,115,.07);display:grid;place-items:center}
.cover img{display:block;width:100%;height:100%;object-fit:contain;background:var(--paper)}
.cover .spin{width:28px;height:28px;border:3px solid rgba(212,163,115,.3);border-top-color:var(--gold);border-radius:50%;animation:sp 1s linear infinite}
@keyframes sp{to{transform:rotate(360deg)}}
.content{display:flex;flex-direction:column;align-items:center;min-width:0;text-align:center}
.eyebrow{margin:0;font:600 clamp(9.5px,1.5vw,11px) var(--sans);letter-spacing:.38em;text-transform:uppercase;color:var(--gold-hi);text-indent:.38em}
.gname{margin:10px 0 0;font:700 clamp(32px,5.4vw,52px)/1.06 var(--display);letter-spacing:.08em;color:#fffaf0;text-shadow:0 2px 0 rgba(0,0,0,.35)}
.gsub{margin:14px 0 0;font:italic 500 clamp(16px,2.3vw,21px)/1.45 var(--display);color:#fbf6ee;max-width:32rem;text-wrap:balance}
.rule{display:flex;align-items:center;gap:11px;width:100%;max-width:280px;margin:16px 0}
.rule i{flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}.rule b{width:5px;height:5px;background:var(--gold);transform:rotate(45deg);flex:none}
.pub{margin:0;font:600 .78rem var(--sans);letter-spacing:.2em;text-transform:uppercase;color:var(--gold-hi)}
.gmeta{margin:8px 0 0;font:clamp(15px,2vw,17px)/1.7 var(--display);color:#d3e0dd}
.open{margin-top:22px;cursor:pointer;font:700 .8rem var(--sans);letter-spacing:.24em;text-transform:uppercase;text-indent:.24em;color:#1d2a29;background:linear-gradient(174deg,#f6e2c4 0%,#dcb185 52%,#bf8f5f 100%);border:0;border-radius:3px;padding:17px 38px;box-shadow:0 14px 30px -14px rgba(212,163,115,.6),inset 0 1px 0 rgba(255,255,255,.4);transition:transform .18s,box-shadow .18s}
.open:hover{transform:translateY(-1.5px);box-shadow:0 20px 38px -14px rgba(212,163,115,.75)}
.open:disabled{opacity:.6;cursor:wait;transform:none}
.links{margin-top:26px;width:100%;max-width:390px;padding-top:22px;border-top:1px solid rgba(212,163,115,.22);display:flex;flex-direction:column;gap:20px}
.lb{display:flex;flex-direction:column;align-items:center;gap:8px}
.ll{margin:0;display:flex;align-items:center;justify-content:center;gap:9px;flex-wrap:wrap;font:600 10.5px var(--sans);letter-spacing:.26em;text-transform:uppercase;color:var(--gold-hi)}
.badge{font:700 8.5px var(--sans);letter-spacing:.16em;color:#1d2a29;background:var(--gold);padding:3.5px 8px;border-radius:2px}
.ln{margin:0;font:15px/1.4 var(--display);color:#c8d8d5}
.lrow{display:flex;align-items:stretch;width:100%;border:1px solid rgba(212,163,115,.3);background:rgba(255,255,255,.03)}
.lb.req .lrow{border:2px solid var(--gold);background:rgba(212,163,115,.14)}
.lurl{flex:1;min-width:0;padding:12px 13px;font:700 13px var(--sans);color:#fff6e6;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lurl:hover{background:rgba(212,163,115,.14)}
.lcopy{flex:none;cursor:pointer;background:rgba(212,163,115,.14);border:0;border-left:1px solid rgba(212,163,115,.3);color:var(--gold-hi);font:600 9.5px var(--sans);letter-spacing:.16em;text-transform:uppercase;padding:0 15px}
.lcopy:hover{background:rgba(212,163,115,.3)}
.home{margin-top:22px;font-size:.85rem;color:var(--muted)}
.draft{position:fixed;top:0;left:0;right:0;z-index:60;background:#c77d1a;color:#fff;text-align:center;font:600 .8rem var(--sans);padding:5px}
@media(max-width:760px){.grid{grid-template-columns:1fr;gap:22px}.cover{max-width:186px}.card{padding:26px 18px;max-height:none;overflow:visible}.gate{place-items:start center}}
/* ===== buku ===== */
.mark{flex:none;text-align:center;padding:12px 0 0;font:600 9.5px var(--sans);letter-spacing:.38em;text-transform:uppercase;color:#7f9a96}
.stage{flex:1;min-height:0;display:grid;place-items:center;padding:12px 16px 4px;position:relative}
.book{position:relative;display:grid;grid-template-columns:1fr 1fr;perspective:2800px;filter:drop-shadow(0 30px 46px rgba(0,0,0,.55))}
.book.single{grid-template-columns:1fr}
.book::before{content:"";position:absolute;inset:-6px;z-index:-1;pointer-events:none;background:linear-gradient(160deg,#f0d3ac,#a87a4d 45%,#e7c193)}
.leaf{position:relative;background:var(--paper);overflow:hidden;cursor:pointer}
.leaf.empty{background:transparent;cursor:default}
.leaf img,.face img{display:block;width:100%;height:100%;object-fit:contain;background:var(--paper)}
.leaf.empty img{visibility:hidden}
.book:not(.single) .leaf::after{content:"";position:absolute;top:0;bottom:0;width:42px;pointer-events:none}
.book:not(.single) .leaf.l::after{right:0;background:linear-gradient(90deg,transparent,rgba(20,30,30,.24))}
.book:not(.single) .leaf.r::after{left:0;background:linear-gradient(270deg,transparent,rgba(20,30,30,.24))}
.leaf.empty::after{display:none}.book.coverL::before{left:calc(50% - 6px)}.book.coverR::before{right:calc(50% - 6px)}
.ld{position:absolute;inset:0;display:grid;place-items:center;pointer-events:none}.ld[hidden]{display:none}.ld .spin{width:26px;height:26px;border:3px solid rgba(29,58,58,.15);border-top-color:#27706f;border-radius:50%;animation:sp 1s linear infinite}
.flip{position:absolute;top:0;height:100%;width:50%;transform-style:preserve-3d;z-index:5;pointer-events:none;transition:transform var(--dur,800ms) cubic-bezier(.45,.02,.33,1)}
.book.single .flip{width:100%;left:0}
.flip.fwd{left:50%;transform-origin:left center}.flip.bwd{left:0;transform-origin:right center}
.book.single .flip.fwd,.book.single .flip.bwd{left:0;transform-origin:left center}
.face{position:absolute;inset:0;backface-visibility:hidden;-webkit-backface-visibility:hidden;background:var(--paper);overflow:hidden}
.face.back{transform:rotateY(180deg)}
.face::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(20,30,30,.28),transparent 34%);opacity:0;transition:opacity .5s}
.flip.turning .face::after{opacity:1}.flip[hidden]{display:none!important}
.bar{flex:none;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;padding:12px 16px calc(14px + env(safe-area-inset-bottom,0px))}
.btn{cursor:pointer;background:rgba(255,255,255,.05);border:1px solid rgba(212,163,115,.32);color:var(--bone);border-radius:3px;font:600 10.5px var(--sans);letter-spacing:.14em;text-transform:uppercase;padding:10px 13px;line-height:1;display:inline-flex;align-items:center;gap:8px;text-decoration:none;transition:background .18s,border-color .18s}
.btn:hover:not(:disabled){background:rgba(212,163,115,.16);border-color:var(--gold)}.btn:disabled{opacity:.3;cursor:default}
.btn.on{background:rgba(212,163,115,.22);border-color:var(--gold);color:var(--gold-hi)}
.btn svg{width:14px;height:14px;flex:none;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn.req{background:var(--gold);border-color:var(--gold);color:#1d2a29}
.counter{font:16px var(--display);color:var(--muted);font-variant-numeric:tabular-nums;min-width:9rem;text-align:center}.counter b{color:var(--gold-hi);font-weight:600}
.slider{-webkit-appearance:none;appearance:none;width:min(280px,36vw);height:2px;background:rgba(212,163,115,.32);outline:none;cursor:pointer}
.slider::-webkit-slider-thumb{-webkit-appearance:none;width:13px;height:13px;border-radius:50%;background:var(--gold);border:0}
.slider::-moz-range-thumb{width:13px;height:13px;border-radius:50%;background:var(--gold);border:0}
:focus-visible{outline:2px solid var(--gold);outline-offset:3px}
.toast{position:fixed;left:50%;bottom:calc(84px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);z-index:70;background:#1d3a3a;border:1px solid rgba(212,163,115,.4);color:var(--bone);font:15px var(--display);padding:9px 18px;opacity:0;transition:opacity .4s;pointer-events:none;white-space:nowrap}
.toast.show{opacity:1}
.err{max-width:420px;text-align:center;font:16px/1.6 var(--display)}
@media(max-width:900px){.bar{gap:6px;padding-inline:10px}.btn{padding:10px}.btn .t{display:none}.slider{width:min(200px,30vw)}.counter{min-width:6.5rem;font-size:15px}.mark{display:none}}
@media(prefers-reduced-motion:reduce){.flip{transition-duration:1ms!important}}
</style></head><body>
<?php if ($preview): ?><div class="draft">Pratinjau admin: buku belum ditampilkan untuk pengunjung (aktifkan di Admin → Publikasi).</div><?php endif; ?>
<div class="gate" id="gate"><div class="card"><div class="grid">
<div class="cover" id="cover"><span class="spin" aria-hidden="true"></span></div>
<div class="content">
<?php if (setting('organizer')): ?><p class="eyebrow"><?= e(tx(setting('organizer'))) ?></p><?php endif; ?>
<h1 class="gname"><?= e(brand()) ?></h1>
<?php if (setting('tagline')): ?><p class="gsub"><?= e(setting('tagline')) ?></p><?php endif; ?>
<div class="rule" aria-hidden="true"><i></i><b></b><i></i></div>
<p class="pub"><?= e(tx($title)) ?></p>
<p class="gmeta"><?= e(tx(setting('date'))) ?><?= setting('location') ? '<br>' . e(setting('location')) : '' ?></p>
<button class="open" id="open" type="button" disabled><?= te('Open Book') ?></button>
<?php if ($xl || $zoom || $zoomHint): ?><div class="links">
<?php if ($xl): ?><div class="lb<?= $req ? ' req' : '' ?>"><p class="ll"><?= e($xlLabel) ?><?php if ($req): ?><span class="badge"><?= te('REQUIRED') ?></span><?php endif; ?></p><?php if (setting('book_link_note')): ?><p class="ln"><?= e(tx(setting('book_link_note'))) ?></p><?php endif; ?>
<div class="lrow"><a class="lurl" href="<?= e($xl) ?>" target="_blank" rel="noopener"><?= e($short($xl)) ?></a><button class="lcopy" type="button" data-copy="<?= e($xl) ?>"><?= te('Copy') ?></button></div></div><?php endif; ?>
<?php if ($zoom): ?><div class="lb"><p class="ll"><?= te('Join Online') ?></p><?php if (setting('online_info')): ?><p class="ln"><?= nl2br(e(tx(setting('online_info')))) ?></p><?php endif; ?>
<div class="lrow"><a class="lurl" href="<?= e($zoom) ?>" target="_blank" rel="noopener"><?= e($short($zoom)) ?></a><button class="lcopy" type="button" data-copy="<?= e($zoom) ?>"><?= te('Copy') ?></button></div></div>
<?php elseif ($zoomHint): ?><div class="lb"><p class="ll"><?= te('Join Online') ?></p><p class="ln"><?= te('The online meeting link is available to verified online participants.') ?> <a href="<?= $u ? $H . 'dashboard.php' : $H . 'login.php' ?>"><?= $u ? te('Open Dashboard') : te('Log in') ?></a></p></div><?php endif; ?>
</div><?php endif; ?>
<a class="home" href="<?= $H ?>">&larr; <?= te('Back to home') ?></a>
</div></div></div></div>

<div class="app" id="app" aria-hidden="true">
<div class="mark"><?= e(brand()) ?> &middot; <?= e(tx($title)) ?></div>
<div class="stage" id="stage"><div class="book" id="book">
<div class="leaf l" id="lL"><img alt=""><div class="ld" hidden><span class="spin"></span></div></div>
<div class="leaf r" id="lR"><img alt=""><div class="ld" hidden><span class="spin"></span></div></div>
<div class="flip" id="flip" hidden><div class="face front"><img alt=""></div><div class="face back"><img alt=""></div></div>
</div></div>
<div class="bar">
<button class="btn" id="prev" type="button" aria-label="<?= te('Previous') ?>"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg><span class="t"><?= te('Previous') ?></span></button>
<span class="counter" id="ctr" aria-live="polite"></span>
<button class="btn" id="next" type="button" aria-label="<?= te('Next') ?>"><span class="t"><?= te('Next') ?></span><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
<input class="slider" id="sl" type="range" min="0" max="0" value="0" aria-label="<?= te('Page') ?>">
<button class="btn on" id="snd" type="button" aria-pressed="true" title="<?= te('Page-turn sound') ?>"><svg viewBox="0 0 24 24"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/></svg><span class="t"><?= te('Sound') ?></span></button>
<?php if ($req): ?><a class="btn req" href="<?= e($xl) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span class="t"><?= e($xlLabel) ?></span></a><?php endif; ?>
<?php if ($zoom): ?><a class="btn" href="<?= e($zoom) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg><span class="t">Zoom</span></a><?php endif; ?>
<a class="btn" href="<?= e($pdf) ?>" download title="<?= te('Download PDF') ?>"><svg viewBox="0 0 24 24"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/></svg><span class="t">PDF</span></a>
<button class="btn" id="fs" type="button" title="<?= te('Full screen') ?>"><svg viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button>
<button class="btn" id="cls" type="button" title="<?= te('Close') ?>"><svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
</div></div>
<div class="toast" id="toast" role="status"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function(){
var C=<?= json_encode($J, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var $=function(i){return document.getElementById(i)};
var gate=$('gate'),app=$('app'),book=$('book'),stage=$('stage'),flip=$('flip'),lL=$('lL'),lR=$('lR'),ctr=$('ctr'),sl=$('sl'),btnOpen=$('open');
var pdf=null,N=0,AR=0.707,urls={},jobs={},spread=true,view=0,busy=false,sound=true,actx=null;
function toast(m){var t=$('toast');t.textContent=m;t.classList.add('show');clearTimeout(t._h);t._h=setTimeout(function(){t.classList.remove('show')},1800)}
document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-copy');
 (navigator.clipboard?navigator.clipboard.writeText(v):Promise.reject()).catch(function(){var x=document.createElement('textarea');x.value=v;document.body.appendChild(x);x.select();try{document.execCommand('copy')}catch(e){}x.remove()}).then(function(){toast(C.copied)},function(){toast(C.copied)})})});
function fail(){var c=$('cover');c.innerHTML='<p class="err" style="padding:16px">'+C.fail+'<br><a href="'+C.pdf+'" download>PDF</a></p>';btnOpen.disabled=true}
if(!window.pdfjsLib){fail();return}
pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
/* render satu halaman (1-based) menjadi URL gambar; hasil disimpan */
function render(n,h){if(n<1||n>N)return Promise.resolve('');if(urls[n])return Promise.resolve(urls[n]);if(jobs[n])return jobs[n];
 jobs[n]=pdf.getPage(n).then(function(pg){var v1=pg.getViewport({scale:1});var dpr=Math.min(window.devicePixelRatio||1,2);var target=Math.max(900,(h||stage.clientHeight)*dpr);var sc=Math.min(target/v1.height,3);var vp=pg.getViewport({scale:sc});
  var cv=document.createElement('canvas');cv.width=Math.round(vp.width);cv.height=Math.round(vp.height);var cx=cv.getContext('2d');cx.fillStyle='#fffdf8';cx.fillRect(0,0,cv.width,cv.height);
  return pg.render({canvasContext:cx,viewport:vp}).promise.then(function(){return new Promise(function(res){cv.toBlob(function(b){urls[n]=URL.createObjectURL(b);cv.width=cv.height=0;res(urls[n])},'image/jpeg',.9)})})}).catch(function(){delete jobs[n];return ''});
 return jobs[n]}
function setImg(el,n){var img=el.querySelector('img'),ld=el.querySelector('.ld');el.classList.toggle('empty',!n);if(!n){img.removeAttribute('src');if(ld)ld.hidden=true;return}
 img.alt=C.page+' '+n;el.dataset.n=n;if(urls[n]){img.src=urls[n];if(ld)ld.hidden=true;return}img.removeAttribute('src');if(ld)ld.hidden=false;render(n).then(function(u){if(el.dataset.n==n&&u){img.src=u;if(ld)ld.hidden=true}})}
/* view → halaman yang tampil: mode dua halaman: 0=[-,1], k=[2k,2k+1] */
function pagesOf(v){if(!spread)return[v+1];var a=2*v,b=2*v+1;return[a>=1&&a<=N?a:0,b<=N?b:0]}
function views(){return spread?Math.floor(N/2)+1:N}
function layout(){var w=stage.clientWidth-32,h=stage.clientHeight-24;spread=window.innerWidth>=900&&window.innerWidth>window.innerHeight*1.05;book.classList.toggle('single',!spread);lR.hidden=!spread;
 var ar=spread?AR*2:AR,bw=w,bh=w/ar;if(bh>h){bh=h;bw=h*ar}book.style.width=Math.floor(bw)+'px';book.style.height=Math.floor(bh)+'px'}
function show(){var p=pagesOf(view);book.classList.toggle('coverL',spread&&!p[0]);book.classList.toggle('coverR',spread&&!p[1]);if(spread){setImg(lL,p[0]);setImg(lR,p[1])}else setImg(lL,p[0]);
 var vis=p.filter(Boolean);ctr.innerHTML=C.page+' <b>'+vis.join('–')+'</b> '+C.of+' '+N;sl.max=views()-1;sl.value=view;$('prev').disabled=view<=0;$('next').disabled=view>=views()-1;
 try{history.replaceState(null,'','#p='+(vis[0]||1))}catch(e){}
 var q=[];for(var d=1;d<=2;d++){q=q.concat(pagesOf(view+d),pagesOf(view-d))}q.forEach(function(n){if(n>0&&n<=N)render(n)})}
function swish(){if(!sound)return;try{actx=actx||new(window.AudioContext||window.webkitAudioContext)();var len=actx.sampleRate*.32,buf=actx.createBuffer(1,len,actx.sampleRate),d=buf.getChannelData(0);
 for(var i=0;i<len;i++){var t=i/len;d[i]=(Math.random()*2-1)*Math.pow(1-t,2.2)*Math.min(1,t*14)*.5}var s=actx.createBufferSource();s.buffer=buf;var f=actx.createBiquadFilter();f.type='bandpass';f.frequency.value=2400;f.Q.value=.6;var g=actx.createGain();g.gain.value=.55;s.connect(f);f.connect(g);g.connect(actx.destination);s.start()}catch(e){}}
function go(to){to=Math.max(0,Math.min(views()-1,to));if(to===view||busy)return;var fwd=to>view,reduce=matchMedia('(prefers-reduced-motion:reduce)').matches;
 var cur=pagesOf(view),nxt=pagesOf(to),fi=flip.querySelector('.front img'),bi=flip.querySelector('.back img');
 if(reduce){view=to;show();return}
 busy=true;swish();var fr,bk;
 if(spread){if(fwd){fr=cur[1];bk=nxt[0];setImg(lR,nxt[1])}else{fr=cur[0];bk=nxt[1];setImg(lL,nxt[0])}}
 else{fr=fwd?cur[0]:nxt[0];bk=0;setImg(lL,fwd?nxt[0]:cur[0])}
 var put=function(im,n){if(n&&urls[n])im.src=urls[n];else im.removeAttribute('src')};put(fi,fr);put(bi,bk);if(fr&&!urls[fr])render(fr).then(function(u){fi.src=u});if(bk&&!urls[bk])render(bk).then(function(u){bi.src=u});
 flip.querySelector('.front').style.visibility=fr?'':'hidden';flip.querySelector('.back').style.visibility=bk?'':'hidden';
 flip.className='flip '+(fwd?'fwd':'bwd');var single=!spread;var from=single?(fwd?0:-180):0,end=single?(fwd?-180:0):(fwd?-180:180);
 flip.style.transition='none';flip.style.transform='rotateY('+from+'deg)';flip.hidden=false;void flip.offsetWidth;flip.style.transition='';flip.classList.add('turning');
 requestAnimationFrame(function(){flip.style.transform='rotateY('+end+'deg)'});
 var done=false,fin=function(){if(done)return;done=true;view=to;show();flip.hidden=true;flip.classList.remove('turning');busy=false};flip.addEventListener('transitionend',fin,{once:true});setTimeout(fin,1100)}
$('prev').onclick=function(){go(view-1)};$('next').onclick=function(){go(view+1)};
sl.oninput=function(){if(!busy){view=+sl.value;show()}};
lL.onclick=function(){if(spread)go(view-1);else go(view+1)};lR.onclick=function(){go(view+1)};
document.addEventListener('keydown',function(e){if(!gate.classList.contains('gone'))return;if(e.key==='ArrowRight'||e.key==='PageDown'){go(view+1);e.preventDefault()}else if(e.key==='ArrowLeft'||e.key==='PageUp'){go(view-1);e.preventDefault()}else if(e.key==='Home')go(0);else if(e.key==='End')go(views()-1);else if(e.key==='Escape'&&!document.fullscreenElement)closeBook()});
var tx0=null;stage.addEventListener('touchstart',function(e){tx0=e.touches[0].clientX},{passive:true});stage.addEventListener('touchend',function(e){if(tx0===null)return;var dx=e.changedTouches[0].clientX-tx0;if(Math.abs(dx)>45)go(view+(dx<0?1:-1));tx0=null},{passive:true});
$('snd').onclick=function(){sound=!sound;this.classList.toggle('on',sound);this.setAttribute('aria-pressed',sound)};
$('fs').onclick=function(){var d=document;if(d.fullscreenElement)d.exitFullscreen();else if(d.documentElement.requestFullscreen)d.documentElement.requestFullscreen().catch(function(){})};
if(!document.documentElement.requestFullscreen)$('fs').hidden=true;
function openBook(){gate.classList.add('gone');app.removeAttribute('aria-hidden');layout();show();$('next').focus()}
function closeBook(){gate.classList.remove('gone');app.setAttribute('aria-hidden','true');btnOpen.focus()}
btnOpen.onclick=openBook;$('cls').onclick=closeBook;
var rt;window.addEventListener('resize',function(){clearTimeout(rt);rt=setTimeout(function(){var first=pagesOf(view).filter(Boolean)[0]||1,was=spread;layout();if(was!==spread)view=spread?Math.floor(first/2):first-1;show()},150)});
pdfjsLib.getDocument({url:C.pdf}).promise.then(function(d){pdf=d;N=d.numPages;return d.getPage(1)}).then(function(pg){var v=pg.getViewport({scale:1});AR=v.width/v.height;
 return render(1,700)}).then(function(u){var c=$('cover');c.innerHTML='';var im=new Image();im.alt='';im.src=u;c.appendChild(im);c.style.aspectRatio=AR;btnOpen.disabled=false;
 var m=location.hash.match(/p=(\d+)/);if(m){var p=Math.max(1,Math.min(N,+m[1]));layout();view=spread?Math.floor(p/2):p-1;if(p>1)openBook()}}).catch(fail);
})();
</script></body></html>
