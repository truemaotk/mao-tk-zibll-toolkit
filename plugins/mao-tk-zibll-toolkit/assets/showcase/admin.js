(()=>{
'use strict';

const boot=window.MaoTKShowcaseAdmin;
const hostDocument=window.document;
const root=hostDocument.querySelector('.mtk-showcase-admin');
if(!boot||!root)return;
const ui={querySelector:s=>root.querySelector(s),querySelectorAll:s=>root.querySelectorAll(s),getElementById:id=>root.querySelector('#'+id),createElement:tag=>hostDocument.createElement(tag),addEventListener:(...args)=>root.addEventListener(...args),get activeElement(){return hostDocument.activeElement},body:root};
const defaults=boot.defaultValue;
const clone=x=>JSON.parse(JSON.stringify(x));
function hydrate(value){const copy=clone(value);copy.cards=copy.cards.map(c=>({...c,imageState:c.imageUrl?'normal':'empty'}));return copy;}
let savedState=hydrate(boot.value),state=hydrate(boot.value),revision=boot.revision,saving=false;
let selectedId = state.cards[0]?.id || null;
const view='editor';
let inspectorTab = 'content';
let previewDevice = 'desktop';
let siteTheme = 'light';
let previewTheme = 'light';
let managerOpen = false;
let pointerDrag = null;
let editGroup = null;
let toastTimer;
let draftTimer;
let undoStack = [];
const app = ui.querySelector('[data-showcase-admin-app]');
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const icon = name => `<i class="icon" data-icon="${esc(name)}" aria-hidden="true"></i>`;
function buttonIcon(slot) {
  const prefix=slot==='two'&&!state.settings.syncButtonIcons?'buttonTwo':'buttonOne';
  const custom=safeImage(state.settings[prefix+'CustomIcon']);
  return custom?`<img class="custom-button-icon" src="${esc(custom)}" alt="" aria-hidden="true" data-icon-prefix="${prefix}" draggable="false">`:icon(state.settings[prefix+'Icon']);
}
const selected = () => state.cards.find(c => c.id === selectedId);
const dirty = () => JSON.stringify(state) !== JSON.stringify(savedState);
const themeFor = preview => state.settings.themeMode === 'auto' ? (preview ? previewTheme : siteTheme) : state.settings.themeMode;
function safeLink(value) { try {const u = new URL(String(value)); return /^(https?:)$/.test(u.protocol) ? u.href : ''; } catch {return '';}}
function safeImage(value) { const s = String(value || ''); return /^assets\/[a-z0-9._/-]+$/i.test(s) || /^data:image\/(png|jpeg|webp|gif);base64,[a-z0-9+/=]+$/i.test(s) ? s : safeLink(s); }
function domain(value) {try{return new URL(value).hostname.replace(/^www\./, '');}catch{return '尚未填写网址';}}
function iconHydrate(root = document) {
  root.querySelectorAll('.icon[data-icon]').forEach(el => {
    const name = /^[a-z0-9-]+$/.test(el.dataset.icon) ? el.dataset.icon : 'circle-help';
    el.style.setProperty('--icon', `url("${boot.baseUrl}icons/${name}.svg")`);
  });
}
function notify(message, undo = false) {
  const t = ui.getElementById('mtk-showcase-toast');
  clearTimeout(toastTimer);
  t.innerHTML = `${icon('check')}<span>${esc(message)}</span>${undo ? '<button data-action="undo">撤销</button>' : ''}`;
  iconHydrate(t); t.classList.add('visible');
  toastTimer = setTimeout(() => t.classList.remove('visible'), 3200);
}
function snapshot(group) {if (!group || editGroup !== group) {undoStack.push(clone(state)); if(undoStack.length > 35) undoStack.shift();} editGroup = group || null;}
function rememberDraft(){updateSaveState();}

function updateSaveState() {
  const label = ui.querySelector('.save-state');
  if(label){label.classList.toggle('is-dirty', dirty()); label.innerHTML = `${icon(dirty() ? 'sliders-horizontal' : 'check-check')}<span>${dirty() ? '有未保存的修改' : '案例库已保存'}</span>`;iconHydrate(label);}
  const button = ui.querySelector('[data-action="save"]'); if(button){button.disabled=!dirty()||saving||!boot.valid;button.textContent=saving?'正在保存…':'保存案例库';}
  ui.querySelectorAll('[data-action="undo"]').forEach(b => b.disabled = !undoStack.length);
}
function previewStyles(theme) {
  const s = state.settings;
  const color = (x, fallback) => /^#[0-9a-f]{6}$/i.test(x) ? x : fallback;
  const num = (x,min,max,fallback) => Math.min(max,Math.max(min,Number.isFinite(Number(x))?Number(x):fallback));
  return `--mtk-showcase-padding-top:${num(s.sectionPaddingTop,0,200,10)}px;--mtk-showcase-padding-bottom:${num(s.sectionPaddingBottom,0,200,10)}px;--columns:${s.columns===2?2:3};--card-radius:${num(s.cardRadius,0,28,12)}px;--card-gap:${num(s.gap,12,40,23)}px;--shot-h:${num(s.imageHeight,160,500,270)}px;--shot-mobile:${num(s.imageHeightMobile,160,500,255)}px;--card-title-size:${num(s.cardTitleSize,14,24,17)}px;--icon-size:${num(s.iconSize,13,24,17)}px;--button-bg:${color(s.primaryBg,'#ff890a')};--button-hover:${color(s.primaryHover,'#e97900')};--card-surface:${color(theme==='dark'?s.cardBgDark:s.cardBgLight,theme==='dark'?'#24282e':'#ffffff')};--border-color:${color(theme==='dark'?s.borderDark:s.borderLight,theme==='dark'?'#373d46':'#e6e8ed')};--title-color:${color(theme==='dark'?s.titleDark:s.titleLight,theme==='dark'?'#f0f1f4':'#252a32')};--max-width:${num(s.maxWidth,720,1440,1200)}px;`;
}
function caseButton(card, two) {
  const s = state.settings;
  if(two ? !card.showButtonTwo : !card.showButtonOne) return '';
  const href = safeLink(two ? card.buttonTwoUrl : (card.buttonOneUrl || card.siteUrl));
  const label = two ? card.buttonTwoText : card.buttonOneText;
  if (!label) return '';
  const target = card.newTab ? ' target="_blank"' : '';
  const rel = ['noopener', 'noreferrer']; if(two ? card.buttonTwoNofollow : card.nofollow) rel.push('nofollow');
  const className = `case-button ${two ? 'secondary' : 'primary'}`;
  const content = `${buttonIcon(two?'two':'one')}<span>${esc(label)}</span>`;
  return href ? `<a class="${className}" data-link-preview="off" href="${esc(href)}"${target} rel="${rel.join(' ')}">${content}</a>` : `<span class="${className}" role="link" aria-disabled="true" title="请先填写按钮链接">${content}</span>`;
}
function fallbackMarkup(error = false) {return `<div class="shot-fallback">${icon('image')}<span>${error?'图片暂时无法加载':'还没有选择预览图片'}</span><small>${error?'案例链接仍可正常打开':'添加网站截图后，在这里预览'}</small></div>`;}
function cardMarkup(card, preview) {
  const s = state.settings;
  const image = safeImage(card.imageUrl);const categoryLabel=card.category||state.categories.find(g=>(card.categoryIds||[]).includes(g.id))?.name||'';
  const hasImage = image && card.imageState !== 'empty' && card.imageState !== 'error';
  const dots=s.showBrowserDots?'<span class="window-dots" aria-hidden="true"><i><b>×</b></i><i><b>−</b></i><i><b>+</b></i></span>':'';
  const addressValue=card.addressText||safeLink(card.siteUrl);
  const address=s.showBrowserAddress?`<label class="browser-domain">${icon(safeLink(card.siteUrl).startsWith('https:')?'lock-keyhole':'globe')}<input class="browser-address-input" type="text" readonly value="${esc(addressValue)}" placeholder="尚未填写网址" aria-label="${esc(card.name)} 的完整网址" title="${esc(addressValue)} · 点击选中完整网址" spellcheck="false" autocomplete="off" dir="ltr"></label>`:'<span class="browser-space"></span>';
  const tools=s.showBrowserTools?`<span class="browser-actions" aria-hidden="true" title="浏览器窗口装饰">${icon('share')}${icon('bookmark')}</span>`:'';
  const bar = s.showBrowserBar ? `<div class="browser-bar">${dots}${address}${tools}</div>` : '';
  const screenshot = hasImage ? `<button class="case-shot" data-shot="${esc(card.id)}" aria-label="预览 ${esc(card.name)} 长截图" aria-pressed="false"><img class="shot-image" src="${esc(image)}" alt="${esc(card.imageAlt || card.name + ' 页面截图')}" loading="lazy" draggable="false">${s.showHint ? `<span class="shot-hint">${icon('mouse-pointer-2')}<span>悬停浏览长图</span></span>`:''}</button>` : fallbackMarkup(card.imageState === 'error');
  const zoom=hasImage?`<button class="image-zoom icon-button" data-action="zoom" data-card="${esc(card.id)}" aria-label="放大 ${esc(card.name)} 截图" title="放大截图">${icon('maximize-2')}</button>`:'';
  return `<article class="case-card" data-case-id="${esc(card.id)}" ${preview&&selectedId===card.id?'data-selected="true"':''}>${bar}<div class="case-media">${screenshot}${zoom}</div><div class="case-body">${categoryLabel ? `<div class="case-kicker">${esc(categoryLabel)}</div>`:''}<h3>${esc(card.name || '未命名案例')}</h3>${card.description?`<p class="case-description">${esc(card.description)}</p>`:''}</div><div class="case-actions">${caseButton(card,false)}${caseButton(card,true)}</div></article>`;
}
function showcaseMarkup(preview = false) {
  const s = state.settings; const theme = themeFor(preview);
  return `<section class="showcase" data-theme="${esc(theme)}" data-width-mode="${s.widthMode==='custom'?'custom':'theme'}" aria-label="独立站案例展示" style="${previewStyles(theme)}">${preview&&s.showHeader?`<header class="showcase-heading"><div><h2>${esc(s.title)}</h2>${s.showDescription?`<p>${esc(s.description)}</p>`:''}</div></header>`:''}${state.cards.length?`<div class="showcase-grid">${state.cards.map(c=>cardMarkup(c,preview)).join('')}</div>`:`<div class="showcase-empty">${icon('layout-grid')}<h3>这里将展示你的案例</h3><p>在后台添加第一个案例，即可预览。</p></div>`}</section>`;
}
function managerMarkup() {
  return `<aside class="case-manager" aria-label="案例管理"><div class="manager-head"><h2>案例列表<span class="count-badge">${state.cards.length}</span></h2><button class="icon-button" data-action="add" title="新增案例" aria-label="新增案例">${icon('plus')}</button></div>${categoryManager()}<ol class="case-list">${state.cards.map(c=>`<li class="case-row ${c.enabled===false?'is-unpublished':''} ${c.id===selectedId?'is-selected':''}" data-row="${esc(c.id)}"><button class="drag-handle" draggable="true" aria-label="拖动排序 ${esc(c.name)}" title="拖动排序 · Alt＋方向键移动">${icon('grip-vertical')}</button><button class="case-row-select" data-action="select" data-card="${esc(c.id)}" aria-label="编辑 ${esc(c.name||'未命名案例')}" ${c.id===selectedId?'aria-current="true"':''}>${c.imageUrl&&c.imageState==='normal'?`<img class="row-thumb" src="${esc(safeImage(c.imageUrl))}" alt="">`:`<span class="row-thumb empty">${icon('image')}</span>`}<span class="row-copy"><b>${esc(c.name||'未命名案例')}</b><small>${esc(c.category||state.categories.filter(g=>(c.categoryIds||[]).includes(g.id)).map(g=>g.name).join(' / ')||'未分类')}</small></span></button></li>`).join('')}</ol><button class="button manager-add" data-action="add">${icon('plus')}新增案例</button><p class="manager-help">拖动左侧手柄调整顺序。<br>也可以选中案例后按 Alt＋↑ / ↓。</p><div class="manager-bottom"><h3>案例库工具</h3><button class="button subtle" data-action="export">${icon('download')}导出当前配置</button><button class="button subtle" data-action="reset">${icon('rotate-ccw')}恢复默认外观</button></div></aside>`;
}
function canvasMarkup() {
  return `<section class="canvas-area" aria-label="实时预览"><div class="preview-toolbar"><span>页面预览</span><div class="preview-tools"><div class="device-switch" role="group" aria-label="预览设备"><button data-action="desktop" aria-label="桌面预览" aria-pressed="${previewDevice==='desktop'}">${icon('monitor')}</button><button data-action="mobile" aria-label="手机预览" aria-pressed="${previewDevice==='mobile'}">${icon('smartphone')}</button></div><button class="icon-button" data-action="preview-theme" aria-label="切换预览浅深色" title="切换预览浅深色">${icon(previewTheme==='light'?'moon':'sun')}</button><span class="editor-theme-badge">${themeFor(true)==='dark'?'深色':'浅色'}</span></div></div><div class="canvas-scroll"><div class="preview-paper ${previewDevice==='mobile'?'mobile':''}" data-theme="${esc(themeFor(true))}"><div class="preview-breadcrumb">页面${icon('chevron-right')}案例展示</div>${showcaseMarkup(true)}</div><div class="preview-label">${icon('eye')}设置会实时更新此预览</div></div></section>`;
}
function field(label, key, value, options = {}) {
  const scope = options.scope || 'card'; const help = options.help?`<small>${esc(options.help)}</small>`:'';
  let control;
  if(options.options) control = `<div class="select-wrap"><select data-scope="${scope}" data-key="${key}" aria-label="${esc(label)}" ${options.disabled?'disabled':''}>${options.options.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select></div>`;
  else if(options.textarea) control = `<textarea data-scope="${scope}" data-key="${key}" aria-label="${esc(label)}" rows="3">${esc(value)}</textarea>`;
  else control = `<input type="${options.type||'text'}" data-scope="${scope}" data-key="${key}" value="${esc(value)}" aria-label="${esc(label)}" ${options.placeholder?`placeholder="${esc(options.placeholder)}"`:''}>`;
  return `<label class="field"><span>${esc(label)}</span>${control}${help}</label>`;
}
function toggle(label,key,value,scope='card') {return `<label class="toggle-row"><span>${esc(label)}</span><span class="switch"><input type="checkbox" data-scope="${scope}" data-key="${key}" aria-label="${esc(label)}" ${value?'checked':''}><i></i></span></label>`;}
function range(label,key,value,min,max,unit='px') {return `<label class="field"><span class="range-heading">${esc(label)}</span><span class="range-inputs"><input type="range" min="${min}" max="${max}" value="${value}" data-scope="settings" data-key="${key}" data-unit="${unit}" aria-label="${esc(label)}"><output>${value}${unit}</output></span></label>`;}
function colorField(label,key,value) {return `<div class="color-field"><span>${esc(label)}</span><span class="color-control"><input class="color-hex" type="text" value="${esc(value)}" data-key="${key}" data-scope="settings" aria-label="${esc(label)}色值" spellcheck="false" maxlength="7"><input type="color" value="${esc(value)}" data-key="${key}" data-scope="settings" aria-label="${esc(label)}"></span></div>`;}
function section(title,body,open=false) {return `<details class="setting-section" ${open?'open':''}><summary>${esc(title)}${icon('chevron-down')}</summary><div class="setting-body">${body}</div></details>`;}
function contentSettings(c) {
  return section('案例内容',field('案例名称','name',c.name)+field('展示标签（可选）','category',c.category)+categoryFields(c)+toggle('在前台展示此案例','enabled',c.enabled)+field('简要说明','description',c.description,{textarea:true})+field('案例网址','siteUrl',c.siteUrl,{type:'url',placeholder:'https://'})+field('地址栏显示文字','addressText',c.addressText,{help:'留空时显示完整案例网址。'}),true)
    +section('预览图片',`${c.imageUrl&&c.imageState==='normal'?`<img class="inspector-image" src="${esc(safeImage(c.imageUrl))}" alt="当前案例预览图片">`:fallbackMarkup(c.imageState==='error')}<div class="image-field-tools"><button class="button" data-action="choose-image">${icon('upload')}${c.imageUrl?'替换图片':'选择图片'}</button><button class="button subtle" data-action="remove-image">移除图片</button></div>${field('图片 ALT','imageAlt',c.imageAlt,{help:'用一句话说明截图内容，帮助读屏与图片检索。'})}`,false)
    +section('操作按钮',toggle('显示查看按钮','showButtonOne',c.showButtonOne)+field('查看按钮文字','buttonOneText',c.buttonOneText)+field('查看按钮链接','buttonOneUrl',c.buttonOneUrl,{type:'url',help:'留空时使用案例网址。'})+toggle('显示咨询按钮','showButtonTwo',c.showButtonTwo)+field('咨询按钮文字','buttonTwoText',c.buttonTwoText)+field('咨询按钮链接','buttonTwoUrl',c.buttonTwoUrl,{type:'url'}),false)
    +section('链接选项',toggle('在新窗口打开','newTab',c.newTab)+toggle('案例链接添加 nofollow','nofollow',c.nofollow)+toggle('咨询链接添加 nofollow','buttonTwoNofollow',c.buttonTwoNofollow),false)
    +`<div class="selection-actions"><div><button class="icon-button" data-action="move-up" aria-label="上移当前案例" title="上移">${icon('arrow-up')}</button><button class="icon-button" data-action="move-down" aria-label="下移当前案例" title="下移">${icon('arrow-down')}</button></div><div><button class="icon-button" data-action="duplicate" aria-label="复制当前案例" title="复制">${icon('copy')}</button><button class="icon-button danger" data-action="remove" aria-label="删除当前案例" title="删除">${icon('trash-2')}</button></div></div>`;
}
function customIconEditor(slot) {
  const s=state.settings,prefix=slot==='two'?'buttonTwo':'buttonOne',label=slot==='two'?'咨询按钮':'查看按钮';
  const shared=slot==='two'&&s.syncButtonIcons;
  const disabled=shared?'disabled':'';
  return `<div class="custom-icon-editor" data-icon-editor="${slot}"><div class="custom-icon-heading"><div><strong>${label}图标</strong><small data-icon-name="${slot}">${esc(shared?'共用查看按钮图标':s[prefix+'IconName']||'使用默认图标')}</small></div><span class="custom-icon-preview" data-custom-icon-preview="${slot}" style="--preview-icon-size:${Number(s.iconSize)||17}px" role="img" aria-label="${label}当前图标">${buttonIcon(slot)}</span></div><div class="icon-source-controls" data-icon-source="${slot}"><div class="icon-upload-row"><button class="button small" data-action="choose-button-icon" data-slot="${slot}" ${disabled}>${icon('upload')}上传图标</button><button class="button small subtle" data-action="reset-button-icon" data-slot="${slot}" ${disabled}>恢复默认</button></div><input type="file" id="button-icon-upload-${slot}" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" data-icon-upload="${slot}" hidden ${disabled}><label class="field"><span>或填写图标链接</span><span class="icon-url-row"><input type="url" id="button-icon-url-${slot}" value="${esc(s[prefix+'IconUrl'])}" aria-label="${label}图标链接" placeholder="https://…/icon.svg" spellcheck="false" ${disabled}><button class="button small" data-action="apply-button-icon-url" data-slot="${slot}" ${disabled}>应用</button></span></label></div><small class="icon-import-status" data-icon-status="${slot}" role="status" aria-live="polite">${shared?'关闭上方共用开关，即可单独设置。':'支持 SVG、PNG、JPG、WebP，最大 512 KB。'}</small></div>`;
}
function styleSettings() {
  const s = state.settings;
  return section('模块上下留白',range('模块上方间距','sectionPaddingTop',s.sectionPaddingTop,0,200)+range('模块下方间距','sectionPaddingBottom',s.sectionPaddingBottom,0,200)+'<p class="setting-help">分别调整整个案例区域的上下留白，默认各10px。不改变卡片内部间距。</p>',true)
    +section('两个按钮的统一图标',`<p class="setting-help">上传你自己的图标，或粘贴图片链接。设置统一应用到所有案例。</p>`+toggle('两个按钮使用相同图标','syncButtonIcons',s.syncButtonIcons,'settings')+customIconEditor('one')+customIconEditor('two')+range('两个按钮图标大小','iconSize',s.iconSize,13,24)+`<p class="setting-help">保留图标原有颜色；上传文件和链接图片都会导入WordPress媒体库。</p>`,true)
    +section('浏览器窗口',toggle('显示浏览器顶栏','showBrowserBar',s.showBrowserBar,'settings')+toggle('显示红黄绿圆点','showBrowserDots',s.showBrowserDots,'settings')+toggle('显示胶囊地址栏','showBrowserAddress',s.showBrowserAddress,'settings')+toggle('显示分享与书签装饰','showBrowserTools',s.showBrowserTools,'settings')+'<p class="setting-help">恢复原版苹果式窗口细节。右上角分享与书签为装饰；放大图片使用截图上的独立入口。</p>',false)
    +section('页面与布局',field('页面标题','title',s.title,{scope:'settings'})+field('页面说明','description',s.description,{scope:'settings',textarea:true})+toggle('显示模块标题','showHeader',s.showHeader,'settings')+toggle('显示说明文字','showDescription',s.showDescription,'settings')+field('桌面列数','columns',s.columns,{scope:'settings',options:[[3,'每行 3 个'],[2,'每行 2 个']]})+field('内容宽度','widthMode',s.widthMode,{scope:'settings',options:[['theme','跟随页面容器'],['custom','自定义最大宽度']]})+(s.widthMode==='custom'?range('内容最大宽度','maxWidth',s.maxWidth,720,1440):'<p class="setting-help">沿用页面可用宽度，不额外缩窄。样板按本站 1440px 外层容器预览；正式接入跟随主题。</p>')+range('卡片间距','gap',s.gap,12,40)+range('卡片圆角','cardRadius',s.cardRadius,0,28)+range('案例标题字号','cardTitleSize',s.cardTitleSize,14,24),false)
    +section('主题与配色',field('显示模式','themeMode',s.themeMode,{scope:'settings',options:[['auto','跟随网站'],['light','固定浅色'],['dark','固定深色']]})+colorField('主按钮背景','primaryBg',s.primaryBg)+colorField('主按钮悬停','primaryHover',s.primaryHover)+colorField('浅色卡片背景','cardBgLight',s.cardBgLight)+colorField('浅色边框','borderLight',s.borderLight)+colorField('浅色标题','titleLight',s.titleLight)+colorField('深色卡片背景','cardBgDark',s.cardBgDark)+colorField('深色边框','borderDark',s.borderDark)+colorField('深色标题','titleDark',s.titleDark)+`<p class="setting-help">默认跟随网站浅深色。可在预览上方切换查看。</p>`,false)
    ;
}
function updateIconControls(){
  const s=state.settings;
  for(const slot of ['one','two']){
    const preview=ui.querySelector(`[data-custom-icon-preview="${slot}"]`);
    if(preview){preview.innerHTML=buttonIcon(slot);preview.style.setProperty('--preview-icon-size',(Number(s.iconSize)||17)+'px');iconHydrate(preview);}
    const shared=slot==='two'&&s.syncButtonIcons;
    ui.querySelectorAll(`[data-icon-source="${slot}"] input,[data-icon-source="${slot}"] button`).forEach(e=>e.disabled=!!shared);
    const name=ui.querySelector(`[data-icon-name="${slot}"]`);if(name)name.textContent=shared?'共用查看按钮图标':s[(slot==='two'?'buttonTwo':'buttonOne')+'IconName']||'使用默认图标';
    const status=ui.querySelector(`[data-icon-status="${slot}"]`);if(status){status.textContent=shared?'关闭上方共用开关，即可单独设置。':'支持 SVG、PNG、JPG、WebP，最大 512 KB。';status.classList.remove('is-error');}
  }
}
async function importButtonIcon(slot,request){
 const prefix=slot==='two'?'buttonTwo':'buttonOne';if(slot==='two'&&state.settings.syncButtonIcons)return;
 const status=ui.querySelector('[data-icon-status="'+slot+'"]');if(status){status.textContent='正在导入图标…';status.classList.remove('is-error');}
 ui.querySelectorAll('[data-icon-source="'+slot+'"] button').forEach(e=>e.disabled=true);
 try{let body,headers={'X-WP-Nonce':boot.nonce};if(request.file){body=new FormData();body.append('icon',request.file);}else{headers['Content-Type']='application/json';body=JSON.stringify({url:request.url});}
 const response=await fetch(boot.restUrl+'/icon',{method:'POST',credentials:'same-origin',headers,body});const result=await response.json();if(!response.ok||!result.asset)throw Error(result.message||'图标导入失败');
 await new Promise((resolve,reject)=>{const img=new Image();img.onload=resolve;img.onerror=()=>reject(Error('图片无法显示，请换一个图标。'));img.src=result.asset;});
 snapshot();state.settings[prefix+'CustomIcon']=result.asset;state.settings[prefix+'IconId']=result.id;state.settings[prefix+'IconUrl']=request.url||'';state.settings[prefix+'IconName']=result.name;
 refreshInspector();refreshPreview();rememberDraft();const updated=ui.querySelector('[data-icon-status="'+slot+'"]');if(updated)updated.textContent='图标已导入WordPress媒体库，全部案例同步更新。';notify('图标已导入媒体库，保存后应用',true);
 }catch(error){if(status){status.textContent=error.message;status.classList.add('is-error');}}
 finally{ui.querySelectorAll('[data-icon-source="'+slot+'"] button').forEach(e=>e.disabled=slot==='two'&&state.settings.syncButtonIcons);}
}

function applyButtonIconUrl(slot){
  const input=ui.getElementById('button-icon-url-'+slot);if(!input)return;
  const url=safeLink(input.value.trim());
  if(!url){input.setAttribute('aria-invalid','true');const status=ui.querySelector(`[data-icon-status="${slot}"]`);if(status){status.textContent='请填写完整的 HTTP 或 HTTPS 图片链接。';status.classList.add('is-error');}input.focus();return;}
  input.removeAttribute('aria-invalid');importButtonIcon(slot,{url});
}
function interactionSettings(c) {
  const s = state.settings;
  return section('长截图预览',toggle('启用长截图预览','scrollEnabled',s.scrollEnabled,'settings')+(c?toggle('当前案例允许滚动','scrollEnabled',c.scrollEnabled):'')+toggle('显示图片操作提示','showHint',s.showHint,'settings')+range('桌面预览高度','imageHeight',s.imageHeight,160,500)+range('手机预览高度','imageHeightMobile',s.imageHeightMobile,160,500)+range('浏览速度','previewSpeed',s.previewSpeed,40,140,'px/s')+`<p class="setting-help">电脑悬停浏览，移开后平滑回到顶部。手机轻点开始，再点回到顶部。放大按钮可打开完整截图。</p>`,true)
    +section('动效与操作',`<p class="setting-help">卡片轻微抬起 2px，按钮不放大。图片预览与访问网站使用独立入口，避免误跳转。</p><p class="setting-help">键盘可聚焦预览区，按 Enter 或空格控制。系统开启“减少动态效果”时停止自动滚动，可手动查看截图。</p>`,true)
    +`<div class="keyboard-note">这里调整的是中央案例库的公共展示配置。保存后会更新WordPress中央案例库，引用这些案例的页面同步使用新配置。</div>`;
}
function inspectorMarkup() {
  const c = selected();
  return `<aside class="inspector" aria-label="组件设置"><div class="inspector-title"><strong>案例展示</strong><p>${c?'正在编辑：'+esc(c.name||'未命名案例'):'选择案例，或添加第一个案例。'}</p></div><div class="inspector-tabs" role="tablist" aria-label="设置分类">${[['content','内容'],['style','样式'],['interaction','交互']].map(([k,t])=>`<button class="inspector-tab" role="tab" data-action="inspector-tab" data-tab="${k}" aria-selected="${inspectorTab===k}" aria-controls="inspector-panel" id="tab-${k}">${t}</button>`).join('')}</div><div class="inspector-content" id="inspector-panel" role="tabpanel" aria-labelledby="tab-${inspectorTab}">${inspectorTab==='content'?(c?contentSettings(c):'<div class="panel-empty">添加一个案例，开始编辑图片、文字和链接。</div>'):inspectorTab==='style'?styleSettings():interactionSettings(c)}</div></aside>`;
}
function renderEditor(){return `<div class="editor-shell"><header class="editor-toolbar"><div class="editor-title"><button class="icon-button mobile-panel-toggle" data-action="toggle-manager" aria-label="切换案例列表与设置面板">${icon('panel-left')}</button><div><h1>案例展示</h1><p>页面组件 / 中央案例库</p></div></div><div class="editor-toolbar-actions"><span class="save-state"></span><button class="icon-button" data-action="undo" aria-label="撤销最近修改" title="撤销">${icon('undo-2')}</button><button class="button export-button" data-action="export">${icon('download')}导出</button><a class="button toolbar-page-link" href="${esc(boot.newPageUrl)}">新建展示页面</a><button class="button primary" data-action="save">保存案例库</button></div></header><div class="editor-workspace ${managerOpen?'show-manager':''}">${managerMarkup()}${canvasMarkup()}${inspectorMarkup()}</div><footer class="editor-footer"><span>创建页面 → 选择“MaoTK 案例展示”模板 → 选择案例分类</span><span>保存后更新所有关联页面</span></footer></div>`;}

function render() {
  app.innerHTML=renderEditor();
  
  iconHydrate(app); wireShots(); updateSaveState();
}
function refreshPreview() {
  
  const paper = ui.querySelector('.preview-paper');
  if(paper){paper.dataset.theme=themeFor(true); const old=paper.querySelector('.showcase'); if(old) old.outerHTML=showcaseMarkup(true);iconHydrate(paper);wireShots(paper);}
  const badge = ui.querySelector('.editor-theme-badge');if(badge) badge.textContent=themeFor(true)==='dark'?'深色':'浅色';
  const themeButton=ui.querySelector('[data-action="preview-theme"]');if(themeButton){themeButton.innerHTML=icon(previewTheme==='light'?'moon':'sun');iconHydrate(themeButton);}
}
function refreshManager() {const old=ui.querySelector('.case-manager');if(old){old.outerHTML=managerMarkup();iconHydrate(ui.querySelector('.case-manager'));}}
function refreshInspector() {const old=ui.querySelector('.inspector');if(old){const openPanels=Array.from(old.querySelectorAll('.setting-section[open] summary')).map(x=>x.textContent.trim());old.outerHTML=inspectorMarkup();const panel=ui.querySelector('.inspector');panel.querySelectorAll('.setting-section').forEach(x=>{if(openPanels.includes(x.querySelector('summary').textContent.trim()))x.open=true;});iconHydrate(panel);}}
function wireShots(root=app) {
  root.querySelectorAll('.case-shot').forEach(shot=>{
    const card=state.cards.find(c=>c.id===shot.dataset.shot);
    const img=shot.querySelector('img');let pinned=false;
    const touchPreview=()=>innerWidth<=600||!!shot.closest('.preview-paper.mobile')||!matchMedia('(hover:hover)').matches;
    const update=()=>{const diff=Math.max(0,img.getBoundingClientRect().height-shot.clientHeight);shot.style.setProperty('--travel',diff+'px');return diff;};
    const enabled=()=>state.settings.scrollEnabled && card?.scrollEnabled && update()>8 && !matchMedia('(prefers-reduced-motion: reduce)').matches;
    const start=(sticky=false)=>{if(!enabled()) return; pinned=sticky;shot.style.setProperty('--preview-duration',Math.max(2,Math.min(10,update()/state.settings.previewSpeed))+'s');shot.classList.add('is-previewing');shot.classList.toggle('is-pinned',pinned);shot.setAttribute('aria-pressed',pinned?'true':'false');const text=shot.querySelector('.shot-hint span');if(text)text.textContent=pinned?'再点回到顶部':'正在浏览长图';};
    const stop=()=>{pinned=false;shot.style.setProperty('--preview-duration','.7s');shot.classList.remove('is-previewing','is-pinned');shot.setAttribute('aria-pressed','false');const text=shot.querySelector('.shot-hint span');if(text)text.textContent=touchPreview()?'轻点浏览长图':'悬停浏览长图';};
    shot.addEventListener('pointerenter',e=>{if(e.pointerType!=='touch'&&!touchPreview()&&!pinned)start();});
    shot.addEventListener('pointerleave',()=>{if(!pinned)stop();});
    shot.addEventListener('click',()=>{if(pinned)stop();else start(true);});
    img.addEventListener('load',update,{once:true});
    img.addEventListener('error',()=>{shot.outerHTML=fallbackMarkup(true);iconHydrate(root);},{once:true});
    const hint=shot.querySelector('.shot-hint span');if(hint&&touchPreview())hint.textContent='轻点浏览长图';
    if(!state.settings.scrollEnabled||!card?.scrollEnabled){shot.style.cursor='default';shot.querySelector('.shot-hint')?.remove();}
    update();
  });
}
function chooseCard(id) {selectedId=id;refreshManager();refreshInspector();refreshPreview();ui.querySelector(`.case-row[data-row="${id}"] .case-row-select`)?.focus();}
function moveCard(id,offset,absolute=false) {
  const from=state.cards.findIndex(c=>c.id===id);const to=absolute?offset:from+offset;
  if(from<0||to<0||to>=state.cards.length||from===to)return;
  const keepManagerFocus=!!ui.activeElement?.closest('.case-manager');
  const positions=new Map(Array.from(ui.querySelectorAll('.case-row')).map(el=>[el.dataset.row,el.getBoundingClientRect().top]));
  snapshot();const [c]=state.cards.splice(from,1);state.cards.splice(to,0,c);refreshManager();refreshPreview();rememberDraft();
  ui.querySelectorAll('.case-row').forEach(el=>{const previous=positions.get(el.dataset.row);if(previous!==undefined&&!matchMedia('(prefers-reduced-motion: reduce)').matches)el.animate([{transform:`translateY(${previous-el.getBoundingClientRect().top}px)`},{transform:'translateY(0)'}],{duration:220,easing:'cubic-bezier(.2,.7,.2,1)'});});
  if(keepManagerFocus)ui.querySelector(`.case-row[data-row="${id}"] .case-row-select`)?.focus();
  notify('案例顺序已更新',true);
}
async function save(){if(saving||!boot.valid)return;const submitted=clone(state);saving=true;updateSaveState();try{const response=await fetch(boot.restUrl,{method:'PUT',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':boot.nonce,'If-Match':revision},body:JSON.stringify(submitted)});const result=await response.json();if(!response.ok||!result.valid)throw Error(result.message||'保存失败，请保留当前修改并重试。');revision=result.revision;savedState=hydrate(result.value);if(JSON.stringify(state)===JSON.stringify(submitted)){state=hydrate(result.value);refreshPreview();}notify('案例库已保存，关联页面已更新');}catch(error){notify(error.message||'保存失败，当前修改仍保留。');}finally{saving=false;updateSaveState();}}

function exportConfig() {const blob=new Blob([JSON.stringify({prototype:'MaoTK Toolkit showcase 1.6.0',...state},null,2)],{type:'application/json'});const url=URL.createObjectURL(blob);const a=ui.createElement('a');a.href=url;a.download='maotk-showcase-config.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);notify('当前案例库配置已导出');}
function showImage(id) {const c=state.cards.find(x=>x.id===id);if(!c||!c.imageUrl)return;const d=ui.getElementById('mtk-showcase-image-dialog');ui.getElementById('mtk-showcase-image-title').textContent=c.name;ui.getElementById('mtk-showcase-dialog-image').src=safeImage(c.imageUrl);ui.getElementById('mtk-showcase-dialog-image').alt=c.imageAlt||c.name;d.dataset.theme=themeFor(view==='editor');iconHydrate(d);d.showModal();d.querySelector('.image-dialog-scroll').scrollTop=0;}
function applyAction(action,el) {
  const c=selected();
  switch(action){
    case 'select':chooseCard(el.dataset.card);break;
    case 'front-theme':siteTheme=siteTheme==='light'?'dark':'light';render();break;
    case 'preview-theme':previewTheme=previewTheme==='light'?'dark':'light';refreshPreview();break;
    case 'desktop':previewDevice='desktop';render();break;
    case 'mobile':previewDevice='mobile';render();break;
    case 'toggle-manager':managerOpen=!managerOpen;render();break;
    case 'inspector-tab':inspectorTab=el.dataset.tab;refreshInspector();break;
    case 'add':{snapshot();const id='MTK-'+Date.now().toString(36).toUpperCase();state.cards.push({...clone(boot.cardDefaults),id,name:'新的案例',category:'',description:'',siteUrl:'',imageUrl:'',imageAlt:'',buttonOneUrl:'',buttonTwoUrl:'',imageState:'empty'});selectedId=id;inspectorTab='content';render();rememberDraft();notify('已添加案例，请填写内容');break;}
    case 'duplicate':if(c){snapshot();const copy={...clone(c),id:'MTK-'+Date.now().toString(36).toUpperCase(),name:c.name+' 副本'};state.cards.splice(state.cards.indexOf(c)+1,0,copy);selectedId=copy.id;render();rememberDraft();notify('已复制当前案例',true);}break;
    case 'remove':if(c){snapshot();state.cards=state.cards.filter(x=>x.id!==c.id);selectedId=state.cards[0]?.id||null;render();rememberDraft();notify('案例已移除',true);}break;
    case 'undo':if(undoStack.length){state=undoStack.pop();editGroup=null;if(!selected())selectedId=state.cards[0]?.id||null;render();rememberDraft();notify('已撤销最近修改');}break;
    case 'reset':snapshot();state.settings=clone(defaults.settings);render();rememberDraft();notify('已恢复默认外观，案例与分类保留',true);break;
    case 'move-up':if(c)moveCard(c.id,-1);break;
    case 'move-down':if(c)moveCard(c.id,1);break;
    case 'save':save();break;
    case 'export':exportConfig();break;
    case 'zoom':showImage(el.dataset.card);break;
    case 'close-image':ui.getElementById('mtk-showcase-image-dialog').close();break;
    case 'choose-image':chooseMedia();break;
    case 'remove-image':if(c){snapshot();c.imageUrl='';c.imageId=0;c.imageState='empty';refreshInspector();refreshManager();refreshPreview();rememberDraft();notify('预览图片已移除',true);}break;
    case 'choose-button-icon':ui.getElementById('button-icon-upload-'+el.dataset.slot)?.click();break;
    case 'apply-button-icon-url':applyButtonIconUrl(el.dataset.slot);break;
    case 'reset-button-icon':{const prefix=el.dataset.slot==='two'?'buttonTwo':'buttonOne';snapshot();state.settings[prefix+'CustomIcon']='';state.settings[prefix+'IconUrl']='';state.settings[prefix+'IconName']='';refreshInspector();refreshPreview();rememberDraft();notify('已恢复默认图标',true);break;}
  }
}
ui.addEventListener('click',e=>{
  if(e.target.matches('.browser-address-input')){e.target.select();return;}
  
  const action=e.target.closest('[data-action]');if(action)applyAction(action.dataset.action,action);
});
function onSettingInput(e) {
  const input=e.target;if(!input.matches('[data-key][data-scope]'))return;
  if(input.classList.contains('color-hex')&&!/^#[0-9a-f]{6}$/i.test(input.value)){input.setAttribute('aria-invalid','true');return;}
  input.removeAttribute('aria-invalid');
  const key=input.dataset.key;const target=input.dataset.scope==='settings'?state.settings:selected();if(!target)return;
  snapshot(input.dataset.scope+':'+selectedId+':'+key);
  target[key]=input.type==='checkbox'?input.checked:(input.type==='range'||key==='columns')?Number(input.value):input.value;
  const output=input.closest('.field')?.querySelector('output');if(output)output.textContent=input.value+(input.dataset.unit||'');
  const colorControl=input.closest('.color-control');if(colorControl)colorControl.querySelectorAll('input').forEach(x=>{if(x!==input)x.value=input.value;});
  if(input.dataset.scope==='settings'&&['buttonOneIcon','buttonTwoIcon','syncButtonIcons','iconSize'].includes(key))updateIconControls();
  if(input.dataset.scope==='card'&&(key==='name'||key==='category')){refreshManager();const title=ui.querySelector('.inspector-title p');if(title)title.textContent='正在编辑：'+(target.name||'未命名案例');}
  if(key==='imageState')refreshManager();
  if(key==='widthMode')refreshInspector();
  refreshPreview();rememberDraft();
}
ui.addEventListener('input',onSettingInput);
ui.addEventListener('change',e=>{
  if(e.target.matches('[data-icon-upload]')){
    const file=e.target.files?.[0];if(!file)return;
    const slot=e.target.dataset.iconUpload;
    if(file.size>512*1024||!(/\.(svg|png|jpe?g|webp)$/i.test(file.name))){const status=ui.querySelector(`[data-icon-status="${slot}"]`);if(status){status.textContent='请选择 512 KB 以内的 SVG、PNG、JPG 或 WebP 图标。';status.classList.add('is-error');}return;}
    importButtonIcon(slot,{file});e.target.value='';return;
  }
});
ui.addEventListener('focusout',e=>{if(e.target.matches('[data-key]'))editGroup=null;if(e.target.matches('.browser-address-input'))e.target.scrollLeft=0;});
ui.addEventListener('wheel',e=>{
  const input=e.target.closest('.browser-address-input');if(!input||input.scrollWidth<=input.clientWidth)return;
  const delta=e.deltaX||(e.shiftKey?e.deltaY:0);if(delta){input.scrollLeft+=delta;e.preventDefault();}
},{passive:false});
ui.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){e.preventDefault();save();}
  if(e.altKey&&['ArrowUp','ArrowDown'].includes(e.key)&&selectedId&&e.target.closest('.case-manager')){e.preventDefault();moveCard(selectedId,e.key==='ArrowUp'?-1:1);}
  if(e.target.matches('.inspector-tab')&&['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();const tabs=['content','style','interaction'];inspectorTab=tabs[(tabs.indexOf(inspectorTab)+(e.key==='ArrowRight'?1:2))%3];refreshInspector();ui.querySelector(`[data-tab="${inspectorTab}"]`)?.focus();}
});
// Pointer tracking supplies the same visible drag feedback for mouse and touch.
ui.addEventListener('dragstart',e=>{if(e.target.closest('.drag-handle'))e.preventDefault();});
ui.addEventListener('pointerdown',e=>{
  const handle=e.target.closest('.drag-handle');if(!handle||e.button!==0)return;
  const row=handle.closest('.case-row');const r=row.getBoundingClientRect();
  pointerDrag={id:row.dataset.row,row,handle,startX:e.clientX,startY:e.clientY,pointerId:e.pointerId,rect:r,to:state.cards.findIndex(c=>c.id===row.dataset.row),ghost:null};
  handle.setPointerCapture(e.pointerId);e.preventDefault();
});
ui.addEventListener('pointermove',e=>{
  const d=pointerDrag;if(!d||d.pointerId!==e.pointerId)return;
  if(!d.ghost&&Math.abs(e.clientY-d.startY)+Math.abs(e.clientX-d.startX)<5)return;
  if(!d.ghost){d.ghost=d.row.cloneNode(true);d.ghost.classList.add('drag-ghost');d.ghost.setAttribute('aria-hidden','true');d.ghost.style.width=d.rect.width+'px';d.ghost.style.left=d.rect.left+'px';ui.body.append(d.ghost);d.row.classList.add('is-dragging');ui.body.classList.add('sorting-cases');}
  d.ghost.style.top=(d.rect.top+e.clientY-d.startY)+'px';
  const rows=Array.from(ui.querySelectorAll('.case-list .case-row'));let nearest=0;let dist=Infinity;
  rows.forEach((row,i)=>{const r=row.getBoundingClientRect();const delta=Math.abs(e.clientY-(r.top+r.height/2));if(delta<dist){dist=delta;nearest=i;}row.classList.remove('drop-target');});
  d.to=nearest;if(rows[nearest]?.dataset.row!==d.id)rows[nearest].classList.add('drop-target');
});
function endPointerDrag(e,cancel=false){
  const d=pointerDrag;if(!d||e.pointerId!==d.pointerId)return;
  pointerDrag=null;d.ghost?.remove();d.row.classList.remove('is-dragging');ui.body.classList.remove('sorting-cases');ui.querySelectorAll('.drop-target').forEach(x=>x.classList.remove('drop-target'));
  if(d.handle.hasPointerCapture(e.pointerId))d.handle.releasePointerCapture(e.pointerId);
  if(d.ghost&&!cancel){moveCard(d.id,d.to,true);ui.querySelector(`.case-row[data-row="${d.id}"] .drag-handle`)?.focus();}
}
ui.addEventListener('pointerup',e=>endPointerDrag(e));
ui.addEventListener('pointercancel',e=>endPointerDrag(e,true));
ui.getElementById('mtk-showcase-image-dialog').addEventListener('click',e=>{if(e.target.id==='mtk-showcase-image-dialog')e.target.close();});
function categoryManager(){
 return `<details class="category-editor"><summary>管理案例分类 · ${state.categories.length}</summary>${state.categories.map(group=>`<div class="category-editor-row"><input type="text" value="${esc(group.name)}" data-category-name="${esc(group.id)}" aria-label="分类名称 ${esc(group.name)}" maxlength="80"><button type="button" class="icon-button danger" data-category-remove="${esc(group.id)}" aria-label="移除分类 ${esc(group.name)}">${icon('trash-2')}</button></div>`).join('')}<button type="button" class="button" data-category-add>添加分类</button><p>分类供页面选择。移除分类后，原来引用该分类的页面显示空状态，不会自动切换为全部案例。</p></details>`;
}
function categoryFields(card){
 return `<div class="field"><span>案例分类</span><div class="category-checks">${state.categories.length?state.categories.map(group=>`<label><input type="checkbox" data-case-category="${esc(group.id)}" ${(card.categoryIds||[]).includes(group.id)?'checked':''}>${esc(group.name)}</label>`).join(''):'<small>先在左侧“管理案例分类”中添加分类。</small>'}</div></div>`;
}
function chooseMedia(){
 if(!window.wp?.media){notify('媒体库暂时无法打开，请刷新管理页后重试。');return;}
 const id=selectedId,frame=wp.media({title:'选择案例截图',button:{text:'使用这张截图'},library:{type:'image'},multiple:false});
 frame.on('select',()=>{const media=frame.state().get('selection').first().toJSON(),card=state.cards.find(c=>c.id===id);if(!card)return;snapshot();card.imageId=media.id;card.imageUrl=media.originalImageURL||media.url;card.imageAlt=media.alt||card.imageAlt||card.name;card.imageState='normal';refreshInspector();refreshManager();refreshPreview();rememberDraft();notify('已选择媒体库截图，保存后应用',true);});
 frame.open();
}
ui.addEventListener('click',e=>{
 const add=e.target.closest('[data-category-add]'),remove=e.target.closest('[data-category-remove]');
 if(add){snapshot();state.categories.push({id:'cat-'+Date.now().toString(36),name:'新分类'});render();ui.querySelector('.category-editor').open=true;ui.querySelector('.category-editor-row:last-of-type input')?.focus();rememberDraft();}
 if(remove){const id=remove.dataset.categoryRemove;snapshot();state.categories=state.categories.filter(c=>c.id!==id);state.cards.forEach(c=>c.categoryIds=(c.categoryIds||[]).filter(v=>v!==id));render();ui.querySelector('.category-editor').open=true;rememberDraft();notify('分类已从草稿中移除，保存后生效',true);}
});
ui.addEventListener('input',e=>{
 if(e.target.matches('[data-category-name]')){const group=state.categories.find(c=>c.id===e.target.dataset.categoryName);if(group){snapshot('category:'+group.id);group.name=e.target.value;refreshInspector();rememberDraft();}}
 if(e.target.matches('[data-case-category]')){const card=selected();if(!card)return;snapshot();const id=e.target.dataset.caseCategory;card.categoryIds=e.target.checked?[...new Set([...(card.categoryIds||[]),id])]:(card.categoryIds||[]).filter(v=>v!==id);refreshPreview();rememberDraft();}
});
window.addEventListener('beforeunload',e=>{if(dirty()){e.preventDefault();e.returnValue='';}});

render();

})();
