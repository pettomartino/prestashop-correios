<script>
	$(document).ready(function() {
		var country = $("#correios_carrier_country");
		country.change(function() {
			if ($("#correios_carrier_state_" + country.val()))
			{
				$(".stateInput.selected").removeClass("selected");
				if ($("#correios_carrier_state_" + country.val()).size())
					$("#correios_carrier_state_" + country.val()).addClass("selected");
				else
					$("#correios_carrier_state_none").addClass("selected");
			}
		});

		$("#configForm").submit(function() {
			$("#correios_carrier_state").val($(".stateInput.selected").val());
		});
	});
</script>
<style>
	.stateInput { display: none; }
	.stateInput.selected { display: block; }
</style>


		<ul id="menuTab">
				<li id="menuTab1" class="menuTabButton selected">{l s='General Settings' mod='correios'}</li>
			</ul>
			<div id="tabList">
				<div id="menuTab1Sheet" class="tabItem selected">

					<form action="{$REQUEST_URI}" method="post" class="form" id="configForm">
						<input type="hidden" name="section" value="general" />
						<fieldset style="border: 0px;">
							<h4>{l s='Address Configuration' mod='correios'}: </h4>
							<label>{l s='Zip / Postal Code' mod='correios'}: </label>
							<div class="margin-form">
								<input type="text" size="20" name="correios_carrier_postal_code" value="{$zip_code}" /><br /><BR>
								<input type="checkbox" name="correios_carrier_display_all" value="on"
								{if myCheckBox eq "on"}
								checked
								{/if}
								>{l s='Display All Available Carriers when Free Shipping.' mod='correios'}<br>
							</div>				
						</fieldset>	
						<div class="margin-form"><input class="button" name="submitSave" type="submit"></div>
					</form>
				</div>
			</div>
			<br clear="left" />
			<br />
			<style>
				#menuTab { float: left; padding: 0; margin: 0; text-align: left; }
				#menuTab li { text-align: left; float: left; display: inline; padding: 5px; padding-right: 10px; background: #EFEFEF; font-weight: bold; cursor: pointer; border-left: 1px solid #EFEFEF; border-right: 1px solid #EFEFEF; border-top: 1px solid #EFEFEF; }
				#menuTab li.menuTabButton.selected { background: #FFF6D3; border-left: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; border-top: 1px solid #CCCCCC; }
				#tabList { clear: left; }
				.tabItem { display: none; }
				.tabItem.selected { display: block; background: #FFFFF0; border: 1px solid #CCCCCC; padding: 10px; padding-top: 20px; }
			</style>
			<script>
				$(".menuTabButton").click(function () {
				  $(".menuTabButton.selected").removeClass("selected");
				  $(this).addClass("selected");
				  $(".tabItem.selected").removeClass("selected");
				  $("#" + this.id + "Sheet").addClass("selected");
				});
			</script>
			{if tab_selected}
			<script>
				  $(".menuTabButton.selected").removeClass("selected");
				  $("#menuTab'.$_GET['id_tab'].'").addClass("selected");
				  $(".tabItem.selected").removeClass("selected");
				  $("#menuTab'.$_GET['id_tab'].'Sheet").addClass("selected");
			</script>
			{/if}