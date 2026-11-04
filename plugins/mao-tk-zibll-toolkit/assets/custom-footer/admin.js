(() => {
  'use strict';
  const config=window.MaoTKFooter;if(!config||!document.querySelector('#mtk-footer-admin'))return;
  const form=document.querySelector('#mtkf-settings-form'), sections=document.querySelector('#mtkf-settings-sections');
  const save=document.querySelector('#mtkf-save-config'), revert=document.querySelector('#mtkf-revert-config'), status=document.querySelector('#mtkf-save-status');
  const frame=document.querySelector('#mtkf-footer-frame'), viewport=document.querySelector('.mtkf-viewport'), stage=document.querySelector('#mtkf-frame-stage');
  let state, baseline, preset, revision, dirty=false, saving=false, renderTimer, renderId=0, frameHeight=520, device='desktop', dark=false;
  const requestedSection=new URLSearchParams(location.search).get('section');
  const initialSection=['display','brand','menus','social','follow','legal','layout'].includes(requestedSection)?requestedSection:'legal';
  const openSections=new Set([initialSection,'social-wechat']);
  const clone=value=>structuredClone(value);
  const get=path=>path.split('.').reduce((value,key)=>value?.[key],state);
  function set(path,value){const parts=path.split('.');const last=parts.pop();const parent=parts.reduce((value,key)=>value[key],state);parent[last]=value;}
  function node(tag,attrs={},text){const element=document.createElement(tag);for(const[key,value]of Object.entries(attrs)){if(key==='class')element.className=value;else element.setAttribute(key,String(value));}if(text!==undefined)element.textContent=text;return element;}
  function message(text,error=false){status.textContent=text;status.style.color=error?'#c54953':'#748196';}
  function syncDirty(announce=true){dirty=JSON.stringify(state)!==JSON.stringify(baseline);const invalid=[...form.querySelectorAll('input,textarea')].some(input=>!input.validity.valid);save.disabled=saving||!dirty||invalid||config.valid!==true;revert.disabled=saving||(!dirty&&!invalid);document.querySelector('#mtkf-changed-label').textContent=invalid?'请检查输入':dirty?'未保存':'已保存';if(!saving&&announce)message(invalid?'请检查标红的输入':dirty?'有未保存的修改':'页脚配置已保存',invalid);}
  function changed(){syncDirty();sections.querySelectorAll('[data-social-index]').forEach(label=>{const social=state.social_links[Number(label.dataset.socialIndex)];label.textContent=social?.url||social?.qr_url?'已配置':'未配置';});clearTimeout(renderTimer);renderTimer=setTimeout(renderPreview,220);}
  function textField(container,label,path,options={}){
    const wrap=node('label',{class:'mtkf-field'}),title=node('span',{},label);
    const input=node(options.multiline?'textarea':'input',{'data-path':path});
    if(!options.multiline)input.type=options.number?'number':'text';
    if(options.number){input.min='24';input.max='120';}
    if(options.placeholder)input.placeholder=options.placeholder;
    input.value=get(path)??'';
    input.autocomplete='off';
    input.addEventListener('input',()=>{
      let value=options.number?Number(input.value):input.value;
      if(options.number){if(!input.validity.valid||!input.value)return;value=Math.max(24,Math.min(120,value));}
      if(options.url){const clean=String(value).trim();const relative=options.image?/^\/(?!\/)[a-zA-Z0-9/_.-]+$/.test(clean)&&!clean.includes('..'):/^\/(?!\/)/.test(clean);let valid=!clean||(!options.external&&(relative||(!options.image&&clean==='#')))||(options.mail&&/^mailto:[^?\s]+@[^?\s]+$/i.test(clean));try{const url=new URL(clean);valid=valid||(['http:','https:'].includes(url.protocol)&&!!url.hostname);}catch{}input.setCustomValidity(valid?'':options.external?'请填写 HTTP(S) 地址'+(options.mail?'或 mailto: 邮箱链接':'')+'，也可留空。':options.image?'请填写有效图片地址、站内路径或留空。':'请填写 HTTP(S) 地址、站内路径或留空。');input.setAttribute('aria-invalid',String(!valid));if(!valid){syncDirty();return;}}
      set(path,value);changed();
    });
    wrap.append(title,input);if(options.help)wrap.append(node('small',{},options.help));container.append(wrap);return input;
  }
  function toggle(container,label,path){const wrap=node('label',{class:'mtkf-switch-row'}),input=node('input',{type:'checkbox','data-path':path});input.checked=!!get(path);input.addEventListener('change',()=>{set(path,input.checked);changed();});wrap.append(node('span',{},label),input);container.append(wrap);return input;}
  function choice(container,label,path,values){const wrap=node('label',{class:'mtkf-field'}),shell=node('span',{class:'mtkf-select-shell'}),select=node('select',{'data-path':path});wrap.append(node('span',{},label));values.forEach(([value,text])=>select.append(node('option',{value},text)));select.value=get(path);select.addEventListener('change',()=>{set(path,select.value);changed();});shell.append(select);wrap.append(shell);container.append(wrap);return select;}
  function rangeField(container,label,path,min,max){
    const wrap=node('label',{class:'mtkf-field mtkf-range-field'}),row=node('span',{class:'mtkf-range-row'}),input=node('input',{type:'range',min,max,step:1,'data-path':path}),output=node('output');
    input.value=get(path);const update=()=>{output.value=input.value+' px';input.setAttribute('aria-valuetext',output.value);input.style.setProperty('--mtkf-range-progress',((Number(input.value)-min)/(max-min)*100)+'%');};update();
    input.addEventListener('input',()=>{set(path,Number(input.value));update();changed();});
    wrap.append(node('span',{},label));row.append(input,output);wrap.append(row);container.append(wrap);return input;
  }
  function button(label,callback,className='mtkf-button mtkf-add'){const b=node('button',{type:'button',class:className},label);b.addEventListener('click',callback);return b;}
  function group(container,title){const g=node('div',{class:'mtkf-subgroup'});g.append(node('h3',{},title));container.append(g);return g;}
  function imageControl(container,label,path,fallback='',resetLabel=''){
    const input=textField(container,label,path,{url:true,image:true,placeholder:'https://… 或从媒体库选择'});
    const row=node('div',{class:'mtkf-image-row'}),image=node('img',{alt:label+'预览',width:54,height:54});
    const update=()=>{const src=get(path)||fallback;image.hidden=!src;if(src)image.src=src;};update();input.addEventListener('input',update);
    const upload=button('选择图片',()=>{
      if(!window.wp?.media){message('媒体库未加载，请刷新后重试。',true);return;}
      const picker=wp.media({title:'选择'+label,button:{text:'使用此图片'},library:{type:'image'},multiple:false});
      const before=input.value;
      picker.on('select',()=>{if(!input.isConnected||input.value!==before)return;const item=picker.state().get('selection').first()?.toJSON();if(!item?.url)return;set(path,item.url);input.value=item.url;input.setCustomValidity('');input.removeAttribute('aria-invalid');update();changed();});picker.open();
    },'mtkf-button');
    upload.setAttribute('aria-label','选择'+label);
    row.append(image,upload,button(resetLabel||(fallback?'使用原图':'清除'),()=>{set(path,'');input.value='';input.setCustomValidity('');input.removeAttribute('aria-invalid');update();changed();},'mtkf-text-button'));container.append(row);
    container.append(node('small',{class:'mtkf-help'},'支持图片地址；建议使用清晰的 PNG 或 WebP。'));
  }
  function linkRows(container,path,maximum){
    const rows=get(path)||[];
    rows.forEach((row,index)=>{
      const box=node('div',{class:'mtkf-link-row'});
      textField(box,'文字',path+'.'+index+'.label');textField(box,'地址',path+'.'+index+'.url',{url:true,placeholder:'https://…'});
      box.append(button('×',()=>{rows.splice(index,1);renderForm();changed();},'mtkf-row-remove'));box.lastChild.setAttribute('aria-label','删除第 '+(index+1)+' 项');container.append(box);
    });
    const add=button('添加链接',()=>{rows.push({label:'新链接',url:''});renderForm();changed();const inputs=sections.querySelectorAll('[data-path="'+path+'.'+(rows.length-1)+'.label"]');inputs[0]?.focus();});add.disabled=rows.length>=maximum;container.append(add);
  }
  function accordion(id,title,build,target=sections,stateLabel=null){
    const section=node('section',{class:'mtkf-section'}),control=node('button',{type:'button',class:'mtkf-section-toggle','aria-expanded':openSections.has(id),'aria-controls':'mtkf-panel-'+id});
    control.append(node('span',{class:'mtkf-section-label'},title));if(stateLabel)control.append(stateLabel);control.append(node('span',{class:'mtkf-chevron','aria-hidden':'true'}));
    const panel=node('div',{class:'mtkf-panel',id:'mtkf-panel-'+id}),content=node('div',{class:'mtkf-section-content'});build(content);panel.append(content);panel.hidden=!openSections.has(id);panel.inert=panel.hidden;
    control.addEventListener('click',()=>{
      const open=control.getAttribute('aria-expanded')!=='true';control.setAttribute('aria-expanded',String(open));open?openSections.add(id):openSections.delete(id);panel.inert=!open;
      if(!open&&panel.contains(document.activeElement))control.focus({preventScroll:true});
      const motion=window.MaoTKDisclosureMotion;
      if(motion)motion.set(panel,content,open,{onFinish:()=>{panel.hidden=control.getAttribute('aria-expanded')!=='true';}});else panel.hidden=!open;
    });
    section.append(control,panel);target.append(section);return section;
  }
  function renderForm(){
    window.MaoTKDisclosureMotion?.destroy(sections);sections.textContent='';
    accordion('display','显示范围',content=>{
      toggle(content,'关于我们','show_about');toggle(content,'三个导航栏目','show_menus');toggle(content,'友情链接','show_friends');toggle(content,'关注我们与二维码','show_follow');
      content.append(node('p',{class:'mtkf-help'},'手机端：关于我们常显，菜单和友链折叠，关注区在版权信息上方。'));
    });
    accordion('brand','关于我们',content=>{
      textField(content,'栏目标题','about_title');textField(content,'网站介绍','about_description',{multiline:true});
    });
    accordion('menus','导航栏目',content=>{state.menu_groups.forEach((item,index)=>{const g=group(content,'栏目 '+(index+1));textField(g,'栏目标题','menu_groups.'+index+'.title');linkRows(g,'menu_groups.'+index+'.links',20);});});
    accordion('social','社交媒体',content=>{
      const appearance=group(content,'统一外观');
      rangeField(appearance,'图标大小','social_icon_size',12,32);
      const base=toggle(appearance,'显示图标底座','social_base_enabled');
      const radius=rangeField(appearance,'底座圆角','social_base_radius',0,24);
      const syncBase=()=>{radius.disabled=!get('social_base_enabled');};base.addEventListener('change',syncBase);syncBase();
      appearance.append(node('p',{class:'mtkf-help'},'0px 为直角，调大可变为圆形。关闭底座仍保留点击区域。'));
      const available=[['wechat','微信'],['whatsapp','WhatsApp'],['telegram','Telegram'],['github','GitHub'],['mail','邮箱']];
      const entries=node('div',{class:'mtkf-social-entries'});content.append(entries);
      available.forEach(([icon,label])=>{
        let index=state.social_links.findIndex(item=>item.icon===icon);
        if(index<0){state.social_links.push({icon,label,url:'',qr_url:'',icon_url:''});index=state.social_links.length-1;}
        const stateLabel=node('span',{class:'mtkf-entry-status','data-social-index':index});
        const updateStatus=()=>{stateLabel.textContent=get('social_links.'+index+'.url')||get('social_links.'+index+'.qr_url')?'已配置':'未配置';};updateStatus();
        const entry=accordion('social-'+icon,label,g=>{
          textField(g,'提示文字','social_links.'+index+'.label');textField(g,icon==='mail'?'邮箱链接':'访问地址','social_links.'+index+'.url',{url:true,external:true,mail:icon==='mail',placeholder:icon==='mail'?'mailto:support@example.com':'https://…（留空不显示）'});
          const localIcons={wechat:'brand-wechat',whatsapp:'brand-whatsapp',telegram:'brand-telegram',github:'brand-github',mail:'mail'};
          imageControl(g,'图标图片','social_links.'+index+'.icon_url',config.assets+'icons/'+localIcons[icon]+'.svg','恢复默认图标');
          if(icon==='wechat')imageControl(g,'悬停二维码','social_links.'+index+'.qr_url');
        },entries,stateLabel);
        entry.classList.add('mtkf-social-entry');
      });
    });
    accordion('follow','关注我们与二维码',content=>{
      textField(content,'栏目标题','social_title');
      const appearance=group(content,'统一边框');
      const border=toggle(appearance,'显示边框','qr_border_enabled');
      const width=rangeField(appearance,'边框粗细','qr_border_width',1,4);
      rangeField(appearance,'外框圆角','qr_border_radius',0,24);
      const syncBorder=()=>{width.disabled=!border.checked;};
      border.addEventListener('change',syncBorder);
      syncBorder();
      appearance.append(node('p',{class:'mtkf-help'},'应用于此栏的所有二维码。边框颜色跟随主题，调节时二维码大小不变。'));
      state.qrcodes.forEach((qr,index)=>{const g=group(content,'二维码 '+(index+1));textField(g,'图片下方文字','qrcodes.'+index+'.label');imageControl(g,'二维码图片地址','qrcodes.'+index+'.url','');textField(g,'点击跳转（可留空）','qrcodes.'+index+'.link',{url:true});});
    });
    accordion('legal','版权与备案',content=>{
      const g=group(content,'底栏信息（并排显示）');toggle(g,'显示版权信息','show_copyright');textField(g,'版权文案','copyright');toggle(g,'显示备案信息','show_icp');textField(g,'备案号','icp_label');textField(g,'备案查询地址','icp_url',{url:true,help:'原站地址为 #。当前按纯文字保留，填写有效地址后才可点击。'});imageControl(g,'备案标识图片','icp_icon_url');toggle(g,'显示查询次数和加载耗时','show_performance');
      g.append(node('p',{class:'mtkf-help'},'性能信息来自当前请求，不作为网站测速结果。默认关闭。'));
    });
    accordion('layout','布局与继承',content=>{
      choice(content,'页脚内容宽度','width_mode',[['theme','跟随页眉'],['full','全宽']]);
      content.append(node('p',{class:'mtkf-help'},'跟随页眉时，栏目和版权栏使用同一居中宽度，两侧等量留白。全宽模式使用可用宽度，背景在两种模式下均铺满。'));
      const box=node('div',{class:'mtkf-readonly'});box.append(node('strong',{},'主题与配色'),node('p',{},'浅色、深色、强调色和边框跟随主题。'));content.append(box);
    });
    sections.removeAttribute('aria-busy');
  }
  async function request(data){
    const body=new URLSearchParams({action:config.action,nonce:config.nonce,operation:data.action==='render'?'preview':data.action});
    if(data.settings)body.set('settings',JSON.stringify(data.settings));if(data.revision)body.set('revision',data.revision);
    const response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',body});
    let result;try{result=await response.json();}catch{throw Error('请求未完成，请刷新后重试。');}
    if(!response.ok||!result.success)throw Error(result.data?.message||'请求失败，请刷新后重试。');return result.data;
  }
  function sizeFrame(){const width=device==='mobile'?390:Number(document.querySelector('#mtkf-preview-width').value);const available=Math.max(260,viewport.clientWidth-40);const fit=document.querySelector('#mtkf-preview-zoom').value==='fit';const scale=fit?Math.min(1,available/width):1;frame.style.width=width+'px';frame.style.height=frameHeight+'px';frame.style.transform='scale('+scale+')';stage.style.width=width*scale+'px';stage.style.height=frameHeight*scale+'px';document.querySelector('#mtkf-preview-scale').textContent=width+'px · '+Math.round(scale*100)+'%';document.querySelector('#mtkf-preview-width').hidden=device==='mobile';}
  function sendTheme(){frame.contentWindow?.postMessage({type:'footer-preview-theme',dark},location.origin);}
  async function renderPreview(){if(!state)return;const id=++renderId;document.querySelector('#mtkf-preview-status').textContent='更新预览…';try{const result=await request({action:'render',settings:state});if(id!==renderId)return;frame.srcdoc=result.html;document.querySelector('#mtkf-preview-status').textContent=dirty?'未保存的修改仅在预览显示':'已与保存配置同步';}catch(error){if(id===renderId)document.querySelector('#mtkf-preview-status').textContent=error.message;}}
  frame.addEventListener('load',()=>{sendTheme();sizeFrame();});
  window.addEventListener('message',event=>{if(event.source!==frame.contentWindow||event.origin!==location.origin||event.data?.type!=='footer-preview-size')return;const height=Number(event.data.height);if(Number.isFinite(height)&&height>0&&height<10000){frameHeight=height;sizeFrame();}});
  new ResizeObserver(sizeFrame).observe(viewport);
  document.querySelectorAll('[data-device]').forEach(button=>button.addEventListener('click',()=>{device=button.dataset.device;document.querySelectorAll('[data-device]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));sizeFrame();}));
  document.querySelectorAll('[data-theme]').forEach(button=>button.addEventListener('click',()=>{dark=button.dataset.theme==='dark';document.querySelectorAll('[data-theme]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));sendTheme();}));
  document.querySelector('#mtkf-preview-zoom').addEventListener('change',sizeFrame);
  document.querySelector('#mtkf-preview-width').addEventListener('change',sizeFrame);
  save.addEventListener('click',async()=>{
    const invalid=[...form.querySelectorAll('input,textarea')].find(input=>!input.validity.valid);
    if(invalid){const panel=invalid.closest('.mtkf-panel');if(panel.hidden)document.querySelector('[aria-controls="'+panel.id+'"]').click();invalid.reportValidity();return;}
    saving=true;syncDirty();message('正在保存…');const sent=clone(state);
    try{const result=await request({action:'save',settings:sent,revision});revision=result.revision;baseline=clone(result.settings);if(JSON.stringify(state)===JSON.stringify(sent)){state=clone(result.settings);renderForm();}syncDirty();message(dirty?'保存完成；仍有后续修改未保存':'页脚配置已保存，前台刷新后生效');}
    catch(error){message(error.message,true);}finally{saving=false;syncDirty(false);}
  });
  revert.addEventListener('click',()=>{state=clone(baseline);renderForm();changed();});
  document.querySelector('#mtkf-load-preset').addEventListener('click',()=>{state=clone(preset);renderForm();changed();message('已读取主题资料，点击保存后生效');});
  document.querySelector('#mtkf-export-config').addEventListener('click',()=>{if(!state)return;const url=URL.createObjectURL(new Blob([JSON.stringify({schema:'maotk-toolkit-footer-v1',settings:state},null,2)],{type:'application/json'}));const a=node('a',{href:url,download:'maotk-footer-settings.json'});a.click();setTimeout(()=>URL.revokeObjectURL(url),500);});
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
  request({action:'read'}).then(data=>{
    state=data.settings;baseline=clone(state);preset=data.preset;revision=data.revision;config.valid=data.valid;
    // Editor rows include all five icons; empty entries are intentionally omitted by the renderer.
    for(const[icon,label]of[['wechat','微信'],['whatsapp','WhatsApp'],['telegram','Telegram'],['github','GitHub'],['mail','邮箱']]){
      if(!state.social_links.some(item=>item.icon===icon))state.social_links.push({icon,label,url:'',qr_url:'',icon_url:''});
    }
    baseline=clone(state);renderForm();syncDirty();renderPreview();if(requestedSection)form.scrollTop=document.querySelector('[aria-controls="mtkf-panel-'+initialSection+'"]').offsetTop-sections.offsetTop;document.querySelector('#mtkf-load-preset').disabled=false;document.querySelector('#mtkf-export-config').disabled=false;
  }).catch(error=>{message(error.message,true);sections.textContent='读取失败，请刷新重试。';});
})();
