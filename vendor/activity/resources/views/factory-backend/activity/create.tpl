<{extends file="factory-backend/extends/create.block.tpl"}>

<{block "head-plus"}>
<{include file="common/uploader.inc.tpl"}>
<{include file="common/editor.inc.tpl"}>
<{/block}>

<{block "inline-script-plus"}>
$('#aid').uploader(640, 400, undefined, undefined, 1);
<{/block}>

<{block "title"}>活动<{/block}>

<{block "name"}>activity<{/block}>

<{block "fields"}>
<{include file="factory-backend/activity/fields.inc.tpl"}>
<{/block}>
