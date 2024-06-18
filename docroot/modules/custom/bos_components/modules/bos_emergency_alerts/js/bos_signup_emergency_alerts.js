/**
 * @file
 */

var BostonEmergencyAlerts = (function () {
  var form = jQuery('#bosAlertForm');
  var email,
    button,
    phone_number,
    first_name,
    last_name,
    call,
    address,
    city,
    state,
    zip_code,
    language,
    text;

  function handleAlertSignup(ev) {
    ev.preventDefault();

    var isValid = validateForm();

    if (isValid) {
      var data = form.serialize();

      button.attr('disabled', true).html('loading');

      jQuery.ajax({
        url: form.attr('action'),
        method: 'post',
        data: data,
        success: function (req) {
          if (req.status == "success") {
            handleSuccess(req);
          }
          else {
            jQuery('#message').append('<div class="t--subinfo t--err m-t100">There was an error. Please try again or email <a href="mailto:feedback@boston.gov">feedback@boston.gov</a>.</div>').show();
          }
        },
        error: function (req, err) {
          button.attr('disabled', false).html('Sign Up');

          if (req.responseJSON && req.responseJSON.errors) {
            jQuery('#message').append('<div class="t--subinfo t--err m-t100">' + req.responseJSON.errors.message + '</div>').show();
          }
          else {
            jQuery('#message').append('<div class="t--subinfo t--err m-t100">There was an error. Please try again or email <a href="mailto:feedback@boston.gov">feedback@boston.gov</a>.</div>').show();
          }
        },
      });
    }
  }

  function handleSuccess(data) {
    form.find('#message, #button').remove();
    form.find('.t--subinfo').remove();
    form.css("visibility", "hidden");
    jQuery('#alert_content').remove();
    jQuery('#bos_alert_message').html(data.contact).show();
    jQuery("#bosAlertForm").remove();
  }

  function validateAddress() {
    var addressFields = {
        "address" : address.val(),
        "city": city.val(),
        "state": state.val(),
        "zip_code" : zip_code.val(),
    };
    var aValues = [];
    for (let [key, value] of Object.entries(addressFields)) {
      if (value == "") {
        aValues.push(`${key}`);
      }
    }
    return aValues;
  }
  function validateForm() {
    var valid = true;

    resetForm();
    var textVal = jQuery("#checkbox-text").prop('checked');
    var callVal = jQuery("#checkbox-call").prop('checked');
    var checkEmailFormat = /^[A-Z0-9_'%=+!`#~$*?^{}&|-]+([\.][A-Z0-9_'%=+!`#~$*?^{}&|-]+)*@[A-Z0-9-]+(\.[A-Z0-9-]+)+$/i;

    if (email.val() == '' && phone_number.val() == '') {
      triggerError(email, "Please enter a valid email or phone number", 'txt-f--err');
      triggerError(phone_number, "Please enter a valid phone number or email", 'txt-f--err');
      valid = false;
    }

    if (first_name.val() == '') {
      triggerError(first_name, "Please enter your first name", 'txt-f--err');
      valid = false;
    }

    if (last_name.val() == '') {
      triggerError(last_name, "Please enter your last name", 'txt-f--err');
      valid = false;
    }

    if (email.val() !== '' && !checkEmailFormat.test(email.val())) {
      triggerError(email, "Email format is invalid", 'txt-f--err');
      valid = false;
    }

    if (phone_number.val() !== '' && textVal == false && callVal == false) {
      triggerError(text_or_call, "Please select text or call", 'txt-f--err');
      valid = false;
    }

    if (validateAddress().length > 0 && validateAddress().length < 4) {
      var aArray = validateAddress();
      jQuery(aArray).each(function (index,value) {
        triggerError(jQuery("#" + value), "Address field (" + value + ") must be complete", 'txt-f--err');
      });
      valid = false;
    }
    return valid;
  }

  function resetForm() {
    jQuery('.t--err').remove();
    jQuery('.txt-l').css({color: ''});
    jQuery('.txt-f,select').css({borderColor: ''});
  }


  function triggerError(el, msg, className) {
    var el = jQuery(el);
    var parent = el.parent();

    parent.append('<div class="t--subinfo t--err m-t100">' + msg + '</div>');
    parent.find('.txt-l').css({color: '#fb4d42'});
    el.css({borderColor: '#fb4d42'});
  }

  function start() {
    jQuery(document).ready(function (){
      email = jQuery('#email');
      phone_number = jQuery('#phone_number');
      first_name = jQuery('#first_name');
      last_name = jQuery('#last_name');
      call = jQuery('#checkbox-call');
      text = jQuery('#checkbox-text');
      address = jQuery('#address');
      city = jQuery('#city');
      state = jQuery('#state');
      zip_code = jQuery('#zip_code');
      language = jQuery('#emergency-alerts-language');
      button = jQuery('#alert_submit');
      form.submit(handleAlertSignup);
      form.find('.txt-f').attr("tabindex","0");
      form.find('.cb-f').attr("tabindex","0");
      form.find('.sel-f').attr("tabindex","0");
      form.find('.btn').attr("tabindex","0");
    });
  }

  return {
    start: start
  }
})()

BostonEmergencyAlerts.start();
