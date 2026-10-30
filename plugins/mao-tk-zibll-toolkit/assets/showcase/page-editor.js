(()=>{'use strict';
 const config=window.MaoTKShowcasePage,Panel=window.wp?.editor?.PluginDocumentSettingPanel||window.wp?.editPost?.PluginDocumentSettingPanel;if(!config||!window.wp?.plugins||!Panel)return;
 const el=wp.element.createElement;
 function PageCategory(){
  const edited=wp.data.useSelect(select=>({type:select('core/editor').getCurrentPostType(),template:select('core/editor').getEditedPostAttribute('template'),meta:select('core/editor').getEditedPostAttribute('meta')||{}}),[]);
  const {editPost}=wp.data.useDispatch('core/editor');if(edited.type!=='page')return null;
  const current=edited.meta[config.metaKey]||'',options=[{label:'全部案例',value:''},...config.categories.map(group=>({label:group.name,value:group.id}))];
  if(current&&!config.categories.some(group=>group.id===current))options.push({label:'原分类已移除，请重新选择',value:current});
  return el(Panel,{name:'mao-tk-showcase-page',title:'MaoTK 案例展示'},
   el(wp.components.SelectControl,{label:'案例分类',value:current,options,onChange:value=>editPost({meta:{...edited.meta,[config.metaKey]:value}}),help:'此页面只关联分类，案例资料在Toolkit后台统一管理。'}),
   edited.template!==config.template?el('p',null,'在页面“模板”中选择“MaoTK 案例展示”，发布后自动显示案例。'):null,
   !config.enabled?el('p',{role:'status'},'案例展示模块未启用；当前选择会保留，前台暂不显示案例。'):null,
   el('a',{href:config.manageUrl,target:'_blank',rel:'noopener'},'打开中央案例库'));
 }
 wp.plugins.registerPlugin('mao-tk-showcase-page',{render:PageCategory});
})();
