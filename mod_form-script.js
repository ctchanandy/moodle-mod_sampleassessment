/* called by ../mod_form.php; used to control assessment editing */

// DOM element object -> rubric's select dropdown
var ru = document.getElementById('id_rubricid');

// Allows Rubric & Points select dropdown's to interact
function updateElem(value, courseid, wwwpath, sesskey){
    var ob = document.getElementById('id_grade'); // old 'points' grading dropdown
    var i = ru.selectedIndex;
    var l = ru.options.length - 2;

    if (i < l && (!isNumeric(value) || value == 0)) {
        ob.disabled = false;
    } else {
        ob.disabled = true;
    }
    
    if (value == 'import') {
        ru.selectedIndex = 0;
        window.open(wwwpath + '/mod/assessment/rubric/mod.php?course=' + courseid + '&action=popuplistview&sesskey=' + sesskey, 'import', 'location=1,status=1,scrollbars=1,width=1000,height=600');
    } else if (value == 'new') {
        ru.selectedIndex = 0;
        window.open(wwwpath + '/mod/assessment/rubric/mod.php?course=' + courseid + '&action=popupcreate&sesskey=' + sesskey, 'new', 'location=1,status=1,scrollbars=1,width=1000,height=600');
    }
}

function changeMode(mode) {
   if (mode == 0) {
      window.location = window.location+'&mode=0';
   } else {
      var myStr=new String(window.location);
      window.location = myStr.replace('&mode=0', '');
   }
}

// Is called from popup windows after adding new rubrics
function addRubric(text, value){
    ru.options[0] = new Option(text,value); 
    ru.selectedIndex = 0;
    updateElem(value);
}

function isNumeric(num){
    var x = (isNaN(num) || num == null);
    var y = (num.toString() == 'true' || num.toString() == 'false');
    return !(x || y);
}

/*
	parseUri 1.2.1
	(c) 2007 Steven Levithan <stevenlevithan.com>
	MIT License
*/
parseUri.options = {
	strictMode: false,
	key: ["source","protocol","authority","userInfo","user","password","host","port","relative","path","directory","file","query","anchor"],
	q:   {
		name:   "queryKey",
		parser: /(?:^|&)([^&=]*)=?([^&]*)/g
	},
	parser: {
		strict: /^(?:([^:\/?#]+):)?(?:\/\/((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?((((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/,
		loose:  /^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/)?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?)(((\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/
	}
};

function parseUri (str) {
    var	o = parseUri.options;
    var m = o.parser[o.strictMode ? "strict" : "loose"].exec(str);
    var uri = {};
    var i = 14;
    
    while (i--) uri[o.key[i]] = m[i] || "";
    
    uri[o.q.name] = {};
    uri[o.key[12]].replace(o.q.parser, function ($0, $1, $2) {
        if ($1) uri[o.q.name][$1] = $2;
    });
    
    return uri;
};

function changeSampleNumber(num) {
   var uriStr = new String(window.location);
   var parsedUri = parseUri(uriStr);
   newUri = parsedUri.protocol+'://'+parsedUri.host+parsedUri.path+'?';
   for (var prop in parsedUri.queryKey) {
      if (prop == 'numsubmission' || prop == 'scrollto') continue;
      newUri += prop+'='+parsedUri.queryKey[prop]+'&';
   }
   
   if (num > 0 && num < 21) {
      newUri += 'numsubmission='+num;
   } else {
      newUri += 'numsubmission=3';
   }
   window.location = newUri;
}

function fileSelected(num, exist, message) {
   var textInput = document.getElementById('id_samplename'+num);
   var fileInput = document.getElementById('id_samplefile'+num);
   
   if (exist) {
      if (!confirm(message)) {
         fileInput.value = '';
         return false;
      }
   }
   var fileInputValue = new String(fileInput.value);
   var fileInputPart = fileInputValue.split('\\');
   var fileNamePart = fileInputPart[fileInputPart.length -1].split('.');
   textInput.value = fileNamePart[0];
}
