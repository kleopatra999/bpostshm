{*
* 2014 Stigmi
*
* @author Stigmi.eu <www.stigmi.eu>
* @copyright 2014 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

{if !$simple_header}

	<script type="text/javascript">
		$(document).ready(function() {
			$('table.{$list_id|escape} .filter').keypress(function(event){
				formSubmit(event, 'submitFilterButton{$list_id|escape}')
			})
		});
	</script>
	{* Display column names and arrows for ordering (ASC, DESC) *}
	{if $is_order_position}
		<script type="text/javascript" src="../js/jquery/plugins/jquery.tablednd.js"></script>
		<script type="text/javascript">
			var token = '{$token|strval}';
			var come_from = '{$list_id|escape}';
			var alternate = {if $order_way == 'DESC'}'1'{else}'0'{/if};
		</script>
		<script type="text/javascript" src="../js/admin-dnd.js"></script>
	{/if}

	<script type="text/javascript">
		$(function() {
			if ($("table.{$list_id|escape} .datepicker").length > 0)
				$("table.{$list_id|escape} .datepicker").datepicker({
					prevText: '',
					nextText: '',
					dateFormat: 'yy-mm-dd'
				});
		});
	</script>


{/if}{* End if simple_header *}

{if $show_toolbar}
	{include file="toolbar.tpl" toolbar_btn=$toolbar_btn toolbar_scroll=$toolbar_scroll title=$title}
{/if}

{if !$simple_header}
	<div class="leadin">{block name="leadin"}{/block}</div>
{/if}

{block name="override_header"}{/block}


{hook h='displayAdminListBefore'}
{if isset($name_controller)}
	{capture name=hookName assign=hookName}display{$name_controller|ucfirst}ListBefore{/capture}
	{hook h=$hookName}
{elseif isset($smarty.get.controller)}
	{capture name=hookName assign=hookName}display{$smarty.get.controller|ucfirst|htmlentities}ListBefore{/capture}
	{hook h=$hookName}
{/if}


{if !$simple_header}
<form method="post" action="{$action|strval}" class="form" autocomplete="off">

	{block name="override_form_extra"}{/block}

	<input type="hidden" id="submitFilter{$list_id|escape}" name="submitFilter{$list_id|escape}" value="0"/>
{/if}
	<table class="table_grid" name="list_table">
		{if !$simple_header}
			<tr>
				<td style="vertical-align: bottom;">
					<span style="float: left;">
						{if $page > 1}
							<input type="image" src="../img/admin/list-prev2.gif" onclick="getE('submitFilter{$list_id|escape}').value=1"/>&nbsp;
							<input type="image" src="../img/admin/list-prev.gif" onclick="getE('submitFilter{$list_id|escape}').value={$page|intval - 1}"/>
						{/if}
						{l s='Page' mod='bpostshm'} <b>{$page|intval}</b> / {$total_pages|intval}
						{if $page < $total_pages}
							<input type="image" src="../img/admin/list-next.gif" onclick="getE('submitFilter{$list_id|escape}').value={$page|intval + 1}"/>&nbsp;
							<input type="image" src="../img/admin/list-next2.gif" onclick="getE('submitFilter{$list_id|escape}').value={$total_pages|intval}"/>
						{/if}
						| {l s='Display' mod='bpostshm'}
						<select name="{$list_id|escape}_pagination" onchange="submit()">
							{* Choose number of results per page *}
							{foreach $pagination AS $value}
								<option value="{$value|intval}"{if $selected_pagination == $value && $selected_pagination != NULL} selected="selected"{elseif $selected_pagination == NULL && $value == $pagination[1]} selected="selected2"{/if}>{$value|intval}</option>
							{/foreach}
						</select>
						/ {$list_total|intval} {l s='result(s)' mod='bpostshm'}
					</span>
					<span style="float: right;">
						<input type="submit" id="submitFilterButton{$list_id|escape}" name="submitFilter" value="{l s='Filter' mod='bpostshm'}" class="button" />
						<input type="submit" name="submitReset{$list_id|escape}" value="{l s='Reset' mod='bpostshm'}" class="button" />
					</span>
					<span class="clear"></span>
				</td>
			</tr>
		{/if}
		<tr>
			<td id="adminbpostorders"{if $simple_header} style="border:none;"{/if}>
				<table
				{if $table_id} id={$table_id|escape}{/if}
				class="table {if $table_dnd}tableDnD{/if} {$list_id|escape}"
				cellpadding="0" cellspacing="0"
				style="width: 100%; margin-bottom:10px;">
					<col width="10px" />
					{foreach $fields_display AS $key => $params}
						<col {if isset($params.width) && $params.width != 'auto'}width="{$params.width|intval}px"{/if}/>
					{/foreach}
					{if $shop_link_type}
						<col width="80px" />
					{/if}
					{if $has_actions}
						<col width="52px" />
					{/if}
					<thead>
						<tr class="nodrag nodrop" style="height: 40px">
							<th class="center">
								{if $has_bulk_actions}
									<input type="checkbox" name="checkme" class="noborder" onclick="checkDelBoxes(this.form, '{$list_id|escape}Box[]', this.checked)" />
								{/if}
							</th>
							{foreach $fields_display AS $key => $params}
								<th {if isset($params.align)} class="{$params.align|escape}"{/if}>
									{if isset($params.hint)}<span class="hint" name="help_box">{$params.hint|escape}<span class="hint-pointer">&nbsp;</span></span>{/if}
									<span class="title_box">
										{$params.title|escape}
									</span>
									{if (!isset($params.orderby) || $params.orderby) && !$simple_header}
										<br />
										<a href="{$currentIndex}&{$list_id|escape}Orderby={$key|urlencode}&{$list_id|escape}Orderway=desc&token={$token}{if isset($smarty.get.$identifier)}&{$identifier}={$smarty.get.$identifier|intval}{/if}">
										<img border="0" src="../img/admin/down{if isset($order_by) && ($key == $order_by) && ($order_way == 'DESC')}_d{/if}.gif" /></a>
										<a href="{$currentIndex}&{$list_id|escape}Orderby={$key|urlencode}&{$list_id|escape}Orderway=asc&token={$token}{if isset($smarty.get.$identifier)}&{$identifier}={$smarty.get.$identifier|intval}{/if}">
										<img border="0" src="../img/admin/up{if isset($order_by) && ($key == $order_by) && ($order_way == 'ASC')}_d{/if}.gif" /></a>
									{elseif !$simple_header}
										<br />&nbsp;
									{/if}
								</th>
							{/foreach}
							{if $shop_link_type}
								<th>
									{if $shop_link_type == 'shop'}
										{l s='Shop' mod='bpostshm'}
									{else}
										{l s='Group shop' mod='bpostshm'}
									{/if}
									<br />&nbsp;
								</th>
							{/if}
							{if $has_actions}
								<th class="center">{l s='Actions' mod='bpostshm'}{if !$simple_header}<br />&nbsp;{/if}</th>
							{/if}
						</tr>
 						{if !$simple_header}
						<tr class="nodrag nodrop filter {if $row_hover}row_hover{/if}" style="height: 35px;">
							<td class="center">
								{if $has_bulk_actions}
									--
								{/if}
							</td>

							{* Filters (input, select, date or bool) *}
							{foreach $fields_display AS $key => $params}
								<td {if isset($params.align)} class="{$params.align}" {/if}>
									{if isset($params.search) && !$params.search}
										--
									{else}
										{if $params.type == 'bool'}
											<select onchange="$('#submitFilterButton{$list_id|escape}').focus();$('#submitFilterButton{$list_id|escape}').click();" name="{$list_id|escape}Filter_{$key}">
												<option value="">-</option>
												<option value="1"{if $params.value == 1} selected="selected"{/if}>{l s='Yes' mod='bpostshm'}</option>
												<option value="0"{if $params.value == 0 && $params.value != ''} selected="selected"{/if}>{l s='No' mod='bpostshm'}</option>
											</select>
										{elseif $params.type == 'date' || $params.type == 'datetime'}
											{l s='From' mod='bpostshm'} <input type="text" class="filter datepicker" id="{$params.id_date|escape}_0" name="{$params.name_date|escape}[0]" value="{if isset($params.value.0)}{$params.value.0|escape}{/if}"{if isset($params.width)} style="width:70px"{/if}/><br />
											{l s='To' mod='bpostshm'} <input type="text" class="filter datepicker" id="{$params.id_date|escape}_1" name="{$params.name_date|escape}[1]" value="{if isset($params.value.1)}{$params.value.1|escape}{/if}"{if isset($params.width)} style="width:70px"{/if}/>
										{elseif $params.type == 'select'}
											{if isset($params.filter_key)}
												<select onchange="$('#submitFilterButton{$list_id|escape}').focus();$('#submitFilterButton{$list_id|escape}').click();" name="{$list_id|escape}Filter_{$params.filter_key|escape}" {if isset($params.width)} style="width:{$params.width|intval}px"{/if}>
													<option value=""{if $params.value == ''} selected="selected"{/if}>-</option>
													{if isset($params.list) && is_array($params.list)}
														{foreach $params.list AS $option_value => $option_display}
															<option value="{$option_value|escape}" {if $params.value != '' && ($option_display == $params.value ||  $option_value == $params.value)} selected="selected"{/if}>{$option_display|escape}</option>
														{/foreach}
													{/if}
												</select>
											{/if}
										{else}
											<input type="text" class="filter" name="{$list_id|escape}Filter_{if isset($params.filter_key)}{$params.filter_key}{else}{$key}{/if}" value="{$params.value|escape:'htmlall':'UTF-8'}" {if isset($params.width) && $params.width != 'auto'} style="width:{$params.width}px"{else}style="width:95%"{/if} />
										{/if}
									{/if}
								</td>
							{/foreach}

							{if $shop_link_type}
								<td>--</td>
							{/if}
							{if $has_actions}
								<td class="center">--</td>
							{/if}
							</tr>
						{/if}
						</thead>