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

<form action="{$REQUEST_URI}" method="post" class="form" id="configForm">
	<input type="hidden" name="section" value="general" />
	<fieldset style="border: 0px;">
		<h4>{$address_configuration}: </h4>
		<label>{$zip_label}: </label>
		<div class="margin-form">
			<input type="text" size="20" name="correios_carrier_postal_code" value="{$zip_code}" /><br /><BR>
			<input type="checkbox" name="correios_carrier_display_all" value="on"
			{if myCheckBox eq "on"}
			checked
			{/if}
			>{$message}<br>
		</div>				
	</fieldset>	
	<div class="margin-form"><input class="button" name="submitSave" type="submit"></div>
</form>