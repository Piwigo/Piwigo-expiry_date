{footer_script}
jQuery(document).ready(function() {
  jQuery("select[name=filter_prefilter]").change(function() {
    jQuery("#expd_options").toggle(jQuery(this).val() == "expiry_date");
  });
});
{/footer_script}

<span id="expd_options" style="{if !isset($filter.prefilter) or $filter.prefilter ne "expiry_date"}display:none{/if}">
  {"Expires in"|translate}<br>
  <label class=""><input type="radio" name="filter_expd" value="0"{if isset($filter.expiry_date_option) && 0 == $filter.expiry_date_option} checked{/if} > {"the past (already expired)"|translate}</label><br>
  <label class=""><input type="radio" name="filter_expd" value="7"{if !isset($filter.expiry_date_option) || (isset($filter.expiry_date_option) && 7 == $filter.expiry_date_option)} checked{/if} > {"less than %s days"|translate:7}</label><br>
  <label class=""><input type="radio" name="filter_expd" value="14"{if isset($filter.expiry_date_option) && 14 == $filter.expiry_date_option} checked{/if}> {"less than %s days"|translate:14}</label><br>
  <label class=""><input type="radio" name="filter_expd" value="30" {if isset($filter.expiry_date_option) && 30 == $filter.expiry_date_option}checked{/if}> {"less than %s days"|translate:30}</label><br>
  <label class=""><input type="radio" name="filter_expd" value="31" {if isset($filter.expiry_date_option) && 31 == $filter.expiry_date_option}checked{/if}> {"more than than %s days"|translate:30}</label><br>
  <label class=""><input type="radio" name="filter_expd" value="-1" {if isset($filter.expiry_date_option) && -1 == $filter.expiry_date_option}checked{/if}> {"No Expiry date"|translate:30}</label>
</span>