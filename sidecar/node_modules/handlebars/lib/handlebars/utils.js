/*jshint -W004 */
import SafeString from "./safe-string";

var escape = {
  "&": "&amp;",
  "<": "&lt;",
  ">": "&gt;",
  '"': "&quot;",
  "'": "&#x27;",
  '`': '&#x60;',
  '=': '&#x3D;',
  // CVE-2015-8861: Additional XSS protection for common attack vectors
  '\u0000': '&#x0;',
  '\u2028': '&#x2028;',
  '\u2029': '&#x2029;'
};

var badChars = /[&<>"'`=\u0000\u2028\u2029]/g;
var possible = /[&<>"'`=\u0000\u2028\u2029]/;

function escapeChar(chr) {
  return escape[chr] || "&amp;";
}

export function extend(obj, value) {
  for(var key in value) {
    if(Object.prototype.hasOwnProperty.call(value, key)) {
      // CVE-2019-19919, CVE-2021-23369: Prevent prototype pollution by blocking dangerous keys
      // Addresses Snyk vulnerabilities: 534988, 469063, 173692, 1279029, 567742
      if (key === '__proto__' || key === 'constructor' || key === 'prototype') {
        continue;
      }
      obj[key] = value[key];
    }
  }
}

export var toString = Object.prototype.toString;

// Sourced from lodash
// https://github.com/bestiejs/lodash/blob/master/LICENSE.txt
var isFunction = function(value) {
  return typeof value === 'function';
};
// fallback for older versions of Chrome and Safari
if (isFunction(/x/)) {
  isFunction = function(value) {
    return typeof value === 'function' && toString.call(value) === '[object Function]';
  };
}
export {isFunction};

export var isArray = Array.isArray || function(value) {
  return (value && typeof value === 'object') ? toString.call(value) === '[object Array]' : false;
};


export function escapeExpression(string) {
  // CVE-2015-8861: XSS protection - don't escape SafeStrings, since they're already safe
  if (string instanceof SafeString) {
    return string.toString();
  } else if (string == null) {
    return "";
  } else if (!string) {
    return string + '';
  }

  // Force a string conversion as this will be done by the append regardless and
  // the regex test will do this transparently behind the scenes, causing issues if
  // an object's to string has escaped characters in it.
  string = "" + string;

  if(!possible.test(string)) { return string; }
  return string.replace(badChars, escapeChar);
}

export function isEmpty(value) {
  if (!value && value !== 0) {
    return true;
  } else if (isArray(value) && value.length === 0) {
    return true;
  } else {
    return false;
  }
}

export function createFrame(object) {
  var frame = extend({}, object);
  frame._parent = object;
  return frame;
}

export function appendContextPath(contextPath, id) {
  return (contextPath ? contextPath + '.' : '') + id;
}

export function isPropertySafe(name) {
  // CVE-2019-19919, CVE-2021-23369, CVE-2019-20920, CVE-2019-20922: Block dangerous properties
  // Addresses Snyk vulnerabilities: 534988, 469063, 173692, 1279029, 567742
  // Block dangerous properties that could lead to prototype pollution, RCE, or XSS
  var dangerousProperties = ['__proto__', 'constructor', 'prototype', '__defineGetter__', '__defineSetter__', '__lookupGetter__', '__lookupSetter__'];
  return dangerousProperties.indexOf(name) === -1;
}
