<p>
  {if isset($expired_on_date)}
  <strong>{"Expiry date"|translate}</strong>{if $expired_on_date != null} ({"expired on"|@translate} {$expired_on_date}){/if}
  {/if}
  <br>
  <input type="hidden" name="expiry_date" value="{if isset($EXPIRY_DATE)}{$EXPIRY_DATE}{/if}">
  <label class="date-input">
    <i class="icon-calendar"></i>
    <input type="text" data-datepicker="expiry_date" data-datepicker-unset="expiry_date_unset" readonly>
  </label>
  <a href="#" class="icon-cancel-circled" id="expiry_date_unset">{"unset"|translate}</a>
</p>