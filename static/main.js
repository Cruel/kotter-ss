$(function(){

	// SIGN UP FORM

	jQuery.validator.addMethod("mobileprovider", function(value, element) {
		return ($("#mobile").val() == "" || value != "");
	}, "This field is required.");

	$("#signupform").validate({
		submitHandler: function(form) {
			$('#submit').attr('disabled', 'disabled');
			$('#loading').fadeIn("fast");
			var target = $("#signupform").attr("action");
			if (target == "")
				target = "?json";
			$.post(target, $(form).serialize(), function(data){
				console.log(data);
				$('.success,.errorlist').slideUp("fast");
				$('#submit').removeAttr('disabled');
				$('#loading').fadeOut("fast");
				if (data.success) {
					$("<div/>", {class:"success", html:data.message})
						.insertBefore(".loginPanel")
						.hide().slideDown("fast");
					$(".loginPanel").slideUp("fast");
					if ("redirect" in data) {
						setTimeout(function(){
							document.location = data.redirect;
						}, data.redirectdelay);
					}
				} else {
					$("<div/>", {class:"errorlist", html:data.message})
						.insertBefore(".loginPanel")
						.hide().slideDown("fast");
				}
			}, "json");
		}
	});

	if ($("#mobileprovider").length == 1)
		$("#mobileprovider").rules("add", "mobileprovider");


	// PAYMENT FORM

	$("#paymenttype").change(function(){
		$(".paymethod").slideUp("fast");
		$("#"+$(this).val()).delay("fast").slideDown("fast");
	});

	jQuery.validator.addMethod("payment", function(value, element) {
		return ($(element).parents("#"+$("#paymenttype").val()).length == 1);
	}, "This field is required.");

	jQuery.validator.addMethod("agency", function(value, element) {
		return this.optional(element) || /^[\x00-\x7F]*$/.test(value);
	}, "Please use only US standard characters.");

	$("#payform").validate({
		submitHandler: function(form) {
			$('#submit').attr('disabled', 'disabled');
			$('#loading').fadeIn("fast");
			// console.log($(form).serialize());
			$.post($("#payform").attr("action"), $(form).serialize(), function(data){
				console.log(data);
				$('.success,.errorlist').slideUp("fast");
				$('#submit').removeAttr('disabled');
				$('#loading').fadeOut("fast");
				if (data.success) {
					$("<div/>", {class:"success", html:data.message})
						.insertBefore(".loginPanel")
						.hide().slideDown("fast");
					$(".loginPanel").slideUp("fast");
				} else {
					$("<div/>", {class:"errorlist", html:data.error})
						.insertBefore(".loginPanel")
						.hide().slideDown("fast");
				}
				if ("redirect" in data) {
					setTimeout(function(){
						document.location = data.redirect;
					}, data.redirectdelay);
				}
			}, "json");
		}
	});

	// $("#mobileprovider").rules("add", "mobileprovider");

	if (defaultplan) {
		$("#plan").val(defaultplan);
	}

});