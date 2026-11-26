{if !empty($EXPD_INTRO)}
{$EXPD_INTRO}

{/if}
{foreach from=$EXPD_IMAGES item=expd_image}
* {$expd_image.NAME|strip_tags}{if !empty($expd_image.AUTHOR)} {$expd_image.AUTHOR}{/if} ({$expd_image.FILE}), {$expd_image.DATE}
{if isset($expd_image.URL)}
  {$expd_image.URL}
{/if}

{/foreach}
{if !empty($EXPD_STATUS)}
{$EXPD_STATUS}
{/if}
{if !empty($EXPD_CUSTOM_CONTENT)}

{$EXPD_CUSTOM_CONTENT}
{/if}
