{combine_css path="plugins/expiry_date/admin/config.css"}

{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.{$themeconf.colorscheme}.css"}

{footer_script}
jQuery(document).ready(function() {
  // like on the user activity page, filters are applied as soon as they
  // change. Filtering is done on server side: build the URL and reload.
  function expd_apply_filters() {
    var params = { page: 'plugin-expiry_date-history' };
    jQuery('#activityMoreFiltersContent').find('select, input').each(function() {
      // empty filters are not sent, to keep URLs short
      if ('' !== this.value) {
        params[this.name] = this.value;
      }
    });
    window.location = 'admin.php?' + jQuery.param(params);
  }

  jQuery('.user-selecter, .action-selecter').selectize({
    allowEmptyOption: true, // "---" removes the filter
    onChange: expd_apply_filters
  });

  jQuery('#activityMoreFiltersContent input')
    .on('change', expd_apply_filters)
    .on('keydown', function(e) {
      if (13 == e.which) {
        expd_apply_filters();
      }
    });

  // same toggle as on the user activity page
  var toggleTriggered = false;
  jQuery('#activityMoreFilters').on('click', function() {
    if (toggleTriggered) {
      return;
    }
    toggleTriggered = true;

    if ('none' == jQuery('#activityMoreFiltersContent').css('display')) {
      jQuery('#activityMoreFilters').addClass('extend-padding');
      jQuery('#activityMoreFiltersContent').slideToggle(function() {
        toggleTriggered = false;
      });
    }
    else {
      jQuery('#activityMoreFiltersContent').slideToggle(function() {
        jQuery('#activityMoreFilters').removeClass('extend-padding');
        toggleTriggered = false;
      });
    }
  });
});
{/footer_script}

{* same design as admin pages "history" and "user_activity": colors come from
   the global classes of admin themes (search-line, *-section, pagination-*,
   activity-*, user-selecter...), layout from admin/config.css *}
<div id="expd_history_page">
{if $expd_history_is_empty}
  <div class="expd-no-result">{'No notification sent yet'|translate}</div>
{else}
  {* same HTML structure as the filters of admin page "user_activity" *}
  <div>
    <div class="activity-header">
      <div class="user_activity_end_options">
        <div id="activityMoreFilters" class="activity-more-filters{if !empty($expd_filters)} extend-padding{/if}">
          <span class="icon-filter"></span>{'Filters'|translate}
        </div>
      </div>
    </div>
    <div id="activityMoreFiltersContent" class="activity-more-filters-content"{if empty($expd_filters)} style="display:none"{/if}>
      <div class="activity-select">
        <span class="activity-select">{'User'|translate}</span>
        <select class="user-selecter" name="filter_user" placeholder="---">
          <option value="">---</option>
        {foreach from=$expd_users item=expd_user}
          <option value="{$expd_user.ID}"{if isset($expd_filters.filter_user) and $expd_filters.filter_user == $expd_user.ID} selected{/if}>{$expd_user.USERNAME|escape} ({$expd_user.NB_EMAILS})</option>
        {/foreach}
        </select>
      </div>

      <div class="activity-select">
        <span class="activity-select">{'Photo ID'|translate}</span>
        <input class="activity-date-selecter" type="number" name="filter_image" min="1" step="1" placeholder="---" value="{if isset($expd_filters.filter_image)}{$expd_filters.filter_image}{/if}">
      </div>

      <div class="activity-select">
        <span class="activity-select">{'Notification'|translate}</span>
        <select class="action-selecter" name="filter_type" placeholder="---">
          <option value="">---</option>
          <option value="prenotification"{if isset($expd_filters.filter_type) and 'prenotification' == $expd_filters.filter_type} selected{/if}>{'Pre-notification'|translate}</option>
          <option value="expiration"{if isset($expd_filters.filter_type) and 'expiration' == $expd_filters.filter_type} selected{/if}>{'On the expiry date'|translate}</option>
        </select>
      </div>

      <div class="activity-select">
        <span class="activity-select">{'Start-Date'|translate}</span>
        <input class="activity-date-selecter" type="date" name="filter_date_min" value="{if isset($expd_filters.filter_date_min)}{$expd_filters.filter_date_min}{/if}" min="{$expd_history_dates.min}" max="{$expd_history_dates.max}">
      </div>

      <div class="activity-select">
        <span class="activity-select">{'End-Date'|translate}</span>
        <input class="activity-date-selecter" type="date" name="filter_date_max" value="{if isset($expd_filters.filter_date_max)}{$expd_filters.filter_date_max}{/if}" min="{$expd_history_dates.min}" max="{$expd_history_dates.max}">
      </div>

      {if !empty($expd_filters)}
      <div class="activity-select expd-filters-reset">
        <a href="{$U_EXPD_HISTORY}" class="icon-cancel-circled">{'Reset'|translate}</a>
      </div>
      {/if}
    </div>
  </div>

  {if empty($expd_emails)}
  <div class="expd-no-result">{'No results'|translate}</div>
  {else}
  <div class="expd-tab-title">
    <div class="expd-date-col">{'Date'|translate}</div>
    <div class="expd-notification-col">{'Notification'|translate}</div>
    <div class="expd-user-col">{'User'|translate}</div>
    <div class="expd-photos-col">{'Photos'|translate}</div>
  </div>

  {foreach from=$expd_emails item=email}
  <div class="search-line expd-line">
    <div class="date-section expd-date-col">
      <span class="expd-line-icon icon-clock icon-blue"></span>
      <div class="expd-infos">
        <span class="expd-bold">{$email.DAY}</span>
        <span>{$email.HOUR}</span>
      </div>
    </div>

    <div class="type-section expd-notification-col">
      {if 'admin' == $email.RECIPIENT}
      <span class="expd-line-icon icon-users-cog icon-purple"></span>
      {else}
      <span class="expd-line-icon icon-users-1 icon-yellow"></span>
      {/if}
      <div class="expd-infos">
        {if isset($email.RECIPIENT_TYPE)}<span class="expd-bold">{$email.RECIPIENT_TYPE}</span>{/if}
        <span>{$email.WHEN}</span>
      </div>
    </div>

    <div class="user-section expd-user-col">
      <div class="expd-infos">
        <span class="expd-bold">{if isset($email.USERNAME)}{$email.USERNAME|escape}{else}#{$email.USER_ID}{/if}</span>
        {if !empty($email.EMAIL)}<span class="expd-ellipsis" title="{$email.EMAIL|escape}">{$email.EMAIL|escape}</span>{/if}
      </div>
    </div>

    <div class="detail-section expd-photos-col">
    {foreach from=$email.IMAGES item=image}
      {if isset($image.DELETED)}
      <span class="expd-photo expd-photo-deleted detail-item-1{if $image.FILTERED} expd-photo-filtered{/if}" title="#{$image.ID}, {'deleted'|translate}">#{$image.ID}</span>
      {else}
      <a class="expd-photo{if $image.FILTERED} expd-photo-filtered{/if}" href="{$image.URL}" title="#{$image.ID}, {$image.NAME|escape}"><img src="{$image.THUMB_URL}" alt="{$image.NAME|escape}"></a>
      {/if}
    {/foreach}
    </div>
  </div>
  {/foreach}

  {if !empty($navbar)}
  <div class="pagination-container">
    {if isset($navbar.URL_PREV)}
    <a class="pagination-arrow left" href="{$navbar.URL_PREV}"><span class="icon-left-open"></span></a>
    {else}
    <span class="pagination-arrow left unavailable"><span class="icon-left-open"></span></span>
    {/if}

    <div class="pagination-item-container">
    {assign var=expd_previous_page value=0}
    {foreach from=$navbar.pages key=page item=url}
      {if $page > $expd_previous_page + 1}<span>...</span>{/if}
      <a href="{$url}"{if $page == $navbar.CURRENT_PAGE} class="actual"{/if}>{$page}</a>
      {assign var=expd_previous_page value=$page}
    {/foreach}
    </div>

    {* "rigth" (sic) is the class name used by the core to flip the arrow *}
    {if isset($navbar.URL_NEXT)}
    <a class="pagination-arrow rigth" href="{$navbar.URL_NEXT}"><span class="icon-left-open"></span></a>
    {else}
    <span class="pagination-arrow rigth unavailable"><span class="icon-left-open"></span></span>
    {/if}
  </div>
  {/if}
  {/if}
{/if}
</div>
