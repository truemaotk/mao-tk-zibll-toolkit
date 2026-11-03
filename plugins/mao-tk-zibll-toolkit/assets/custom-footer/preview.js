(() => {
  const previewOrigin=new URL(document.baseURI).origin;
  let last=0;
  function report() {
    const root=document.querySelector('.mtk-footer-component,.frame-disabled');
    const height=root?Math.ceil(root.getBoundingClientRect().height+26):300;
    if(height!==last){last=height;parent.postMessage({type:'footer-preview-size',height},previewOrigin);}
  }
  window.addEventListener('message',event=>{
    if(event.source!==parent||event.origin!==previewOrigin||event.data?.type!=='footer-preview-theme')return;
    document.body.classList.toggle('dark-theme',event.data.dark===true);report();
  });
  new ResizeObserver(report).observe(document.body);
  window.addEventListener('load',report);report();
})();
