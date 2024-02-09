{footer_script}
jQuery(document).ready(function() {
  $("input[name=remove_expiry_date]").click(function () {
    jQuery("#set_expiry_date").toggle(!jQuery(this).is(':checked'));
  });
});
{/footer_script}

<label class="font-checkbox"><span class="icon-check"></span><input type="checkbox" name="remove_expiry_date"> {'remove expiry date'|@translate}</label><br>
<div id="set_expiry_date">
  <input type="hidden" name="expiry_date" value="{if isset($EXPIRY_DATE)}{$EXPIRY_DATE}{/if}">
  <label class="date-input">
    <i class="icon-calendar"></i>
    <input type="text" data-datepicker="expiry_date" readonly>
  </label>
</div>