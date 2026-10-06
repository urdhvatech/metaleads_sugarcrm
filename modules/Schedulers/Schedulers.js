/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */


function checkFormPre(formId) {
    validateCronInterval(formId);
    var noError = check_form(formId);

    if (noError) {
        return true;
    } else {
        toggleAdv('true');
        return false;
    }
}

function validateCronInterval(formId) {
    var fieldIsValid = function(value, min, max) {
        var inRange = function(value, min, max) {
            return (value >= min && value <= max);
        }
        // Check for *
        if (value == '*') {
            return true;
        }
        // Check for interval syntax
        var result = /^\*\/(\d+)$/.exec(value);
        if (result && result[0] && inRange(result[1], min, max)) {
            return true;
        }
        // Check for ranges/lists
        var sets = value.split(',');
        for (var i = 0; i < sets.length; i++) {
            result = /^(\d+)(-(\d+))?$/.exec(sets[i])
            // eslint-disable-next-line max-len
            if (!result || !result[0] || !inRange(result[1], min, max) || (result[3] && !inRange(result[3], min, max))) {
                return false;
            }
        }
        return true;
    }
    var cronFields = {
        mins: {min: 0, max: 59},
        hours: {min: 0, max: 23},
        day_of_month: {min: 1, max: 31},
        // eslint-disable-next-line no-tabs
        months:	{min: 1, max: 12},
        day_of_week: {min: 0, max: 7},
    }
    var valid = true;
    // eslint-disable-next-line guard-for-in
    for (field in cronFields) {
        removeFromValidate(formId, field);
        // eslint-disable-next-line max-len
        if (document[formId][field] && !fieldIsValid(document[formId][field].value, cronFields[field].min, cronFields[field].max)) {
            valid = false;
            addToValidate(formId, field, 'error', true, '{$MOD.ERR_CRON_SYNTAX}');
        } else {
            addToValidate(formId, field, 'verified', true, '{$MOD.ERR_CRON_SYNTAX}');
        }
    }
    return valid;
}

function toggleAdv(onlyAdv) {
    var thisForm = document.getElementById('EditView');
    var crontab = document.getElementById('crontab');
    var simple = document.getElementById('simple');
    var adv = document.getElementById('advTable');
    var use = document.getElementById('use_adv');

    if (crontab.style.display == 'none' || onlyAdv == 'true') { // show advanced
        crontab.style.display = '';
        adv.style.display = '';
        simple.style.display = 'none';
        use.value = 'true';
    } else {
        crontab.style.display = 'none';
        adv.style.display = 'none';
        simple.style.display = '';
        use.value = 'false';
    }

    for (i=0; i<thisForm.elements.length; i++) {
        if (thisForm.elements[i].disabled) {
            thisForm.elements[i].disabled = false;
        }
    }
}

function getDaysElements() {
    return {
        'toggle': document.getElementById('all'),
        'mon': document.getElementById('mon'),
        'tue': document.getElementById('tue'),
        'wed': document.getElementById('wed'),
        'thu': document.getElementById('thu'),
        'fri': document.getElementById('fri'),
        'sat': document.getElementById('sat'),
        'sun': document.getElementById('sun'),
    }
}

function handleDaysToggle(event) {
    const dayId = $(event.target).attr('id');
    const daysOfWeek = getDaysElements();
    const everyDay = daysOfWeek['toggle'];

    delete daysOfWeek['toggle'];

    if (daysOfWeek.hasOwnProperty(dayId)) {
        everyDay.checked = false;
    }
}

function toggleAllDays(newValue) {
    const dayElements = getDaysElements();

    Object.keys(dayElements).forEach(function(dayName) {
        if (dayName === 'toggle') {
            return;
        }
        dayElements[dayName].checked = newValue;
    });
}

function allDays() {
    const dayElements = getDaysElements();

    if (dayElements.toggle.checked) {
        toggleAllDays(true);
    } else {
        toggleAllDays(false);
    }
}

function updateVisibility() {
    if ($('#adv_interval').is(':checked')) {
        $('#job_interval_advanced').parent().parent().show();
        $('#job_interval_basic').parent().parent().hide();
        $('#LBL_ADV_OPTIONS').show();
    } else {
        $('#job_interval_advanced').parent().parent().hide();
        $('#job_interval_basic').parent().parent().show();
        $('#LBL_ADV_OPTIONS').hide();
    }
}

function initScheduler() {
    //
    if (typeof(adv_interval) != 'undefined' && adv_interval) { // eslint-disable-line camelcase
        $('#adv_interval').prop('checked', true);
    }
}

$('#EditView_tabs').ready(function() {
    initScheduler();
    updateVisibility();
});
$('#adv_interval').ready(function() {
    $('#adv_interval').click(updateVisibility);
});
$('#mon, #tue, #wed, #thu, #fri, #sat, #sun').ready(
    function() {
        $('#mon, #tue, #wed, #thu, #fri, #sat, #sun').click(handleDaysToggle);
    },
);
