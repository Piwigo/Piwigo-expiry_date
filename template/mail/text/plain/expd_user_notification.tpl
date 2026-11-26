{$EXPD_INTRO}

{foreach from=$EXPD_IMAGES item=expd_image}
* {$expd_image.NAME|strip_tags} {$expd_image.AUTHOR} ({$expd_image.FILE}), {$expd_image.DATE}
{if isset($expd_image.URL)}
  {$expd_image.URL}
{/if}

{/foreach}
{$EXPD_STATUS}
{if !empty($EXPD_CUSTOM_CONTENT)}

{$EXPD_CUSTOM_CONTENT}
{/if}
