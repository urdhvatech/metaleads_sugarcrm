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
/**
 * Time.js
 */
 
/*
 * Time constructor
 * @param time 
 * @param fieldname
 * @param timeformat
 * @param tabindex
 */
function Time (timeval, field, timeformat, tabindex) {
    this.timeval = timeval;
    if(typeof this.timeval == "undefined") {
       this.datetime = "";
    }

    this.fieldname = field;
    //Get hours and minutes and adjust as necessary
    this.hrs = parseInt(timeval.substring(0,2), 10);
    this.mins = parseInt(timeval.substring(3,5), 10);

    //A safety scan to make sure hrs and minutes are formatted correctly
	if (this.mins > 0 && this.mins < 15) {
		this.mins = 15;
	} else if (this.mins > 15 && this.mins < 30) {
		this.mins = 30;
	} else if (this.mins > 30 && this.mins < 45) {
		this.mins = 45;
	} else if (this.mins > 45) {
		this.hrs += 1;
		this.mins = 0;
	} //if-else


    this.timeformat = timeformat;  //23:00 || 11:00
    this.tabindex = tabindex == null || isNaN(tabindex) ? 1 : tabindex;
    
    // Calculate other derived values
    this.timeseparator = this.timeformat.substring(2,3);
    this.has12Hours = /^11/.test(this.timeformat);
    this.hasMeridiem = /am|pm/i.test(this.timeformat);
	if(this.hasMeridiem) {
	   this.pm = /pm/.test(this.timeformat);
    }
    this.meridiem = this.hasMeridiem ? trim(this.timeval.substring(5)) : '';
    
    // Store callback for event delegation
    this.callback = null;
}

/**
 * html
 * This function renders the HTML form elements for this widget
 */
Time.prototype.html = function(callback) {
    // Store callback for event delegation
    this.callback = callback;
    
	//Now render the items
    // jscs:disable
    var text = '<select class="datetimecombo_time" size="1" id="' + this.fieldname + '_hours" tabindex="'
        + this.tabindex + '">';
    // jscs:enable
	var h1 = this.has12Hours ? 1 : 0;
	var h2 = this.has12Hours ? 12 : 23;
	text += '<option></option>';
	for(i=h1; i <= h2; i++) {
	    val = i < 10 ? "0" + i : i;
	    text += '<option value="' + val + '" ' + (i == this.hrs ? "SELECTED" : "") +  '>' + val + '</option>';
	}
	
	text += '\n</select>&nbsp;';
	text += this.timeseparator;
    // jscs:disable
    text += '\n&nbsp;<select class="datetimecombo_time" size="1" id="' + this.fieldname + '_minutes" tabindex="'
        + this.tabindex + '">';
    // jscs:enable
	text += '\n<option></option>';
	text += '\n<option value="00" ' + (this.mins == 0 ? "SELECTED" : "") + '>00</option>';
	text += '\n<option value="15" ' + (this.mins == 15 ? "SELECTED" : "") + '>15</option>';
	text += '\n<option value="30" ' + (this.mins == 30 ? "SELECTED" : "") + '>30</option>';
	text += '\n<option value="45" ' + (this.mins == 45 ? "SELECTED" : "") + '>45</option>';
	text += '\n</select>';
	
	if(this.hasMeridiem) {
		text += '\n&nbsp;';
        // jscs:disable
        text += '\n<select class="datetimecombo_time" size="1" id="' + this.fieldname + '_meridiem" tabindex="'
            + this.tabindex + '">';
        // jscs:enable
		if(this.allowEmptyHM){
			text += '\n<option></option>';
		}
		text += '\n<option value="' + (this.pm ? "am" : "AM") + '" ' + (/am/i.test(this.meridiem) ? "SELECTED" : "") + '>' + (this.pm ? "am" : "AM") + '</option>';
		text += '\n<option value="' + (this.pm ? "pm" : "PM") + '" ' + (/pm/i.test(this.meridiem) ? "SELECTED" : "") + '>' + (this.pm ? "pm" : "PM") + '</option>';
		text += '\n</select>';
	}
	
	// Add event delegation after rendering
	this.setupEventDelegation();
	
	return text;
};

/**
 * setupEventDelegation
 * Sets up event delegation for time field changes
 */
Time.prototype.setupEventDelegation = function() {
    var self = this;
    var container = document.getElementById(this.fieldname + '_hours').parentNode;
    
    // Remove existing event listeners if any
    this.removeEventDelegation();
    
    // Add event delegation for change events
    container.addEventListener('change', function(e) {
        if (e.target.classList.contains('datetimecombo_time')) {
            self.update();
            if (self.callback) {
                self.callback();
            }
        }
    });
};

/**
 * removeEventDelegation
 * Removes event delegation when needed
 */
Time.prototype.removeEventDelegation = function() {
    var container = document.getElementById(this.fieldname + '_hours').parentNode;
    if (container) {
        container.removeEventListener('change', this.update);
    }
};

/**
 * update
 * This method handles events on the hour, minute and meridiem elements for the widget
 *
 */
Time.prototype.update = function() {
	id = this.fieldname + '_hours';
	h = window.document.getElementById(id).value;
	id = this.fieldname + '_minutes';
	m = window.document.getElementById(id).value;
	
	if(h == "" && m == "") {
		document.getElementById(this.fieldname).value = "";
		return;
	}
	newdate = h + this.timeseparator  + m;

	if(this.hasMeridiem) {
	   ampm = document.getElementById(this.fieldname + "_meridiem").value;
	   newdate += ampm;
	}
	document.getElementById(this.fieldname).value = newdate;
};
