(()=>{'use strict';
 if(window.MaoTKToolkitShowcaseView){window.MaoTKToolkitShowcaseView.refresh();return;}
 const mounted=new Map();let dialog=null,returnFocus=null;
 const reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
 function theme(root){const mode=root.dataset.themeMode;root.dataset.theme=mode==='dark'||mode==='auto'&&(document.body.classList.contains('dark-theme')||document.documentElement.classList.contains('dark-theme'))?'dark':'light';}
 function close(){if(dialog?.open)dialog.close();}
 function zoom(button){
  const root=button.closest('[data-mao-tk-showcase]'),card=button.closest('.case-card'),image=card.querySelector('.shot-image');if(!image||!image.complete||!image.naturalWidth)return;
  if(!dialog){dialog=document.createElement('dialog');dialog.className='mtk-showcase-dialog';dialog.setAttribute('aria-labelledby','mao-tk-showcase-dialog-title');
   dialog.innerHTML='<div class="image-dialog-head"><div><strong id="mao-tk-showcase-dialog-title"></strong><span>页面截图预览</span></div><button type="button" class="icon-button" data-mao-tk-showcase-close aria-label="关闭截图预览"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg></button></div><div class="image-dialog-scroll"><img alt=""></div>';
   document.body.append(dialog);dialog.querySelector('button').addEventListener('click',close);dialog.addEventListener('click',e=>{if(e.target===dialog)close();});dialog.addEventListener('close',()=>{const focus=returnFocus;returnFocus=null;if(focus?.isConnected)focus.focus({preventScroll:true});dialog.querySelector('img').removeAttribute('src');});
  }
  returnFocus=button;dialog.dataset.theme=root.dataset.theme;dialog.querySelector('strong').textContent=card.querySelector('h3').textContent;dialog.querySelector('img').src=image.currentSrc||image.src;dialog.querySelector('img').alt=image.alt;dialog.showModal();dialog.querySelector('.image-dialog-scroll').scrollTop=0;
 }
 function mount(root){
  if(mounted.has(root))return;theme(root);let observers=[];
  root.querySelectorAll('.case-shot').forEach(shot=>{
   const image=shot.querySelector('.shot-image'),card=shot.closest('.case-card');let pinned=false;
   const touch=()=>!matchMedia('(hover:hover)').matches;
   const travel=()=>{const distance=Math.max(0,image.getBoundingClientRect().height-shot.clientHeight);shot.style.setProperty('--travel',distance+'px');return distance;};
   const enabled=()=>root.dataset.scrollEnabled!=='false'&&card.dataset.scrollEnabled!=='false'&&!reduced()&&travel()>8;
   const label=()=>shot.querySelector('.shot-hint span');
   function stop(){pinned=false;shot.style.setProperty('--preview-duration','.7s');shot.classList.remove('is-previewing','is-pinned');shot.setAttribute('aria-pressed','false');if(label())label().textContent=touch()?'轻点浏览长图':'悬停浏览长图';}
   function start(sticky){if(!enabled())return;pinned=sticky;shot.style.setProperty('--preview-duration',Math.max(2,Math.min(10,travel()/(Number(root.dataset.previewSpeed)||80)))+'s');shot.classList.add('is-previewing');shot.classList.toggle('is-pinned',pinned);shot.setAttribute('aria-pressed',String(pinned));if(label())label().textContent=pinned?'再点回到顶部':'正在浏览长图';}
   shot.addEventListener('pointerenter',e=>{if(e.pointerType!=='touch'&&!touch()&&!pinned)start(false);});shot.addEventListener('pointerleave',()=>{if(!pinned)stop();});shot.addEventListener('click',()=>pinned?stop():start(true));
   function failure(){if(!shot.isConnected)return;const fallback=document.createElement('div');fallback.className='shot-fallback';const text=document.createElement('span');text.textContent='图片暂时无法加载';const note=document.createElement('small');note.textContent='案例链接仍可正常打开';fallback.append(text,note);shot.replaceWith(fallback);card.querySelector('[data-showcase-zoom]')?.remove();}
   image.addEventListener('load',travel);image.addEventListener('error',failure,{once:true});
   if(image.complete&&!image.naturalWidth)failure();else travel();
   if(root.dataset.scrollEnabled==='false'||card.dataset.scrollEnabled==='false'){shot.style.cursor='default';shot.querySelector('.shot-hint')?.remove();}else if(label()&&touch())label().textContent='轻点浏览长图';
   if(window.ResizeObserver){const observer=new ResizeObserver(()=>{travel();});observer.observe(shot);observers.push(observer);}
  });
  root.addEventListener('click',e=>{const address=e.target.closest('.browser-address-input');if(address){address.select();return;}const button=e.target.closest('[data-showcase-zoom]');if(button)zoom(button);});
  root.addEventListener('focusout',e=>{if(e.target.matches('.browser-address-input'))e.target.scrollLeft=0;});
  root.addEventListener('wheel',e=>{const input=e.target.closest('.browser-address-input');if(!input||input.scrollWidth<=input.clientWidth)return;const delta=e.deltaX||(e.shiftKey?e.deltaY:0);if(delta){input.scrollLeft+=delta;e.preventDefault();}},{passive:false});
  mounted.set(root,()=>observers.forEach(observer=>observer.disconnect()));
 }
 function init(){document.querySelectorAll('[data-mao-tk-showcase]').forEach(mount);}
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
 const observer=new MutationObserver(changes=>{for(const record of changes){for(const node of record.addedNodes){if(node.nodeType!==1)continue;if(node.matches('[data-mao-tk-showcase]'))mount(node);node.querySelectorAll('[data-mao-tk-showcase]').forEach(mount);}}for(const [root,cleanup]of mounted){if(!root.isConnected){cleanup();mounted.delete(root);if(returnFocus&&!returnFocus.isConnected)close();}}});
 observer.observe(document.body,{childList:true,subtree:true});
 const themes=new MutationObserver(()=>{for(const root of mounted.keys())theme(root);if(dialog?.open&&returnFocus?.isConnected)dialog.dataset.theme=returnFocus.closest('[data-mao-tk-showcase]').dataset.theme;});
 themes.observe(document.body,{attributes:true,attributeFilter:['class']});themes.observe(document.documentElement,{attributes:true,attributeFilter:['class']});
 window.MaoTKToolkitShowcaseView={refresh:init};
})();
