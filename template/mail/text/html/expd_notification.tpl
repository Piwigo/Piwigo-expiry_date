{if !empty($EXPD_INTRO)}
<p>{$EXPD_INTRO}</p>
{/if}

{foreach from=$EXPD_IMAGES item=expd_image}
<table style="margin:15px 0;border-collapse:collapse;">
  <tr>
{if isset($expd_image.THUMB_URL)}
    <td style="padding:0 15px 0 0;vertical-align:top;">
      {if isset($expd_image.URL)}<a href="{$expd_image.URL}">{/if}<img src="{$expd_image.THUMB_URL}" alt="{$expd_image.NAME|strip_tags|escape}" style="max-width:144px;max-height:144px;border:0;">{if isset($expd_image.URL)}</a>{/if}
    </td>
{/if}
    <td style="padding:0;vertical-align:top;">
      <strong>{if isset($expd_image.URL)}<a href="{$expd_image.URL}">{$expd_image.NAME}</a>{else}{$expd_image.NAME}{/if}</strong><br>
{if !empty($expd_image.AUTHOR)}
      {$expd_image.AUTHOR|escape}<br>
{/if}
      {$expd_image.FILE|escape}<br>
      {$expd_image.DATE}
    </td>
  </tr>
</table>
{/foreach}

{if !empty($EXPD_STATUS)}
<p>{$EXPD_STATUS}</p>
{/if}
{if !empty($EXPD_CUSTOM_CONTENT)}

<p>{$EXPD_CUSTOM_CONTENT|escape|nl2br}</p>
{/if}
