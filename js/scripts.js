/*
* Author: Greg London
* VES WordPress Ticketing Plugin
* http://greglondon.info
*/

// delete toggle
$(document).ready(function() {									
	$("a[name^='del-']").each(function() {
		$(this).click(function() {
			if( $("#" + this.name).is(':hidden') ) {
				$("#" + this.name).toggle('slow');
			} else {
				$("#" + this.name).toggle('slow');
			}			
			return false;
		});
	});
});

// DatePicker
$(document).ready(function () {
    $("#datepicker").datepicker({
        changeMonth: true,
        changeYear: true
    });
});