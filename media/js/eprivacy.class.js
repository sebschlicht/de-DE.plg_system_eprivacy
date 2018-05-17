(function ($) {
    var ePrivacyClass = function (options) {
        var root = this;
        this.vars = {
            accepted: false,
            displaytype: 'message',
            policyurl: '',
            media: '',
            autoopen: true,
            modalclass: '',
            modalwidth: '600',
            modalheight: '400',
            lawlink: '',
            version: 0,
            root: '',
            cookie: {
                domain:null,
                path:null
            },
            npstorage: null
        };
        var construct = function (options) {
            if (!$('div.plg_system_eprivacy_module').length && options.displaytype !== 'cookieblocker') {
                console.log('The EU e-Privacy Directive extension REQUIRES the eprivacy module to be published.');
                return;
            }
            Object.assign(root.vars, options);
            root.vars.npstorage = new npstorage();
            var decline = parseInt(root.getDataValue());
            if (decline === 1 || decline === 2 || !root.vars.autoopen) {
                root.hideMessage();
            } else {
                root.showMessage();
            }
            $.ajaxSetup({'cache': 'false'});
            initElements();
        };
        this.translate = function (constant) {
            return Joomla.JText._('PLG_SYS_EPRIVACY_' + constant);
        };
        var initElements = function () {
            $('button.plg_system_eprivacy_agreed').click(function () {
                root.acceptCookies();
            });
            $('button.plg_system_eprivacy_accepted').click(function () {
                root.unacceptCookies();
            });
            $('button.plg_system_eprivacy_declined').click(function () {
                root.declineCookies();
            });
            $('button.plg_system_eprivacy_reconsider').click(function () {
                root.undeclineCookies();
            });
        };
        this.acceptCookies = function () {
            root.setDataValue(2);
            $.getJSON(root.vars.root, {
                option: 'com_ajax',
                plugin: 'eprivacy',
                format: 'raw',
                method: 'accept',
                country: root.vars.country
            }).done(function (response) {
                if (response) {
                    window.location.reload();
                }
            });
        };
        this.unacceptCookies = function () {
            var r = confirm(root.translate('CONFIRMUNACCEPT'));
            if (r === true) {
                root.delete_cookie('plg_system_eprivacy');
                root.setDataValue(1);
                $.getJSON(root.vars.root, {
                    option: 'com_ajax',
                    plugin: 'eprivacy',
                    format: 'raw',
                    method: 'decline'
                }).done(function (response) {
                    if (response) {
                        window.location.reload();
                    }
                });
            }
        };
        this.delete_cookie = function( name ) {
        	document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;'
            +(root.vars.cookie.path!==null?'path='+root.vars.cookie.path+';':'')
            +(root.vars.cookie.domain!==null?'domain='+root.vars.cookie.domain+';':'');
        };
        this.declineCookies = function () {
            root.setDataValue(1);
            root.hideMessage();
        };
        this.undeclineCookies = function () {
            root.setDataValue(0);
            root.showMessage();
        };
        this.showMessage = function () {
            $('div.plg_system_eprivacy_declined').each(function (index) {
                $(this).hide();
            });
            $('div.plg_system_eprivacy_accepted').each(function (index) {
                $(this).hide();
            });
            switch (root.vars.displaytype) {
                case 'message':
                case 'module':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).show();
                    });
                    break;
                case 'confirm':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    displayConfirm();
                    break;
                case 'modal':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    displayModal();
                    break;
                case 'ribbon':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    displayRibbon();
                    break;
                case 'cookieblocker':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    break;
            }
        };
        this.hideMessage = function () {
            if (parseInt(root.getDataValue()) === 1) {
                $('div.plg_system_eprivacy_declined').show();
                $('div.plg_system_eprivacy_accepted').hide();
            } else {
                $('div.plg_system_eprivacy_declined').hide();
                $('div.plg_system_eprivacy_accepted').show();
            }
            switch (root.vars.displaytype) {
                case 'message':
                case 'confirm':
                case 'module':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    break;
                case 'modal':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    $('#eprivacyModal').modal('hide');
                    break;
                case 'ribbon':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    $('div.activebar-container').each(function (index) {
                        $(this).remove();
                    });
                    break;
                case 'cookieblocker':
                    $('div.plg_system_eprivacy_message').each(function (index) {
                        $(this).hide();
                    });
                    $('div.plg_system_eprivacy_declined').each(function (index) {
                        $(this).hide();
                    });
                    $('div.plg_system_eprivacy_accepted').each(function (index) {
                        $(this).hide();
                    });
                    break;
            }
        };
        this.setDataValue = function (value) {
            root.vars.npstorage.set(btoa(window.location.hostname + '.plg_system_eprivacy_decline'), value);
        };
        this.getDataValue = function () {
            return root.vars.npstorage.get(btoa(window.location.hostname + '.plg_system_eprivacy_decline'), 0);
        };
        var displayRibbon = function () {
            var ribbon = $('<div class="activebar-container"/>').appendTo(document.body);
            var message = $('<p>' + root.translate('MESSAGE') + '</p>').appendTo(ribbon);
            var decline = $('<button class="decline '+root.vars.declineclass+'">' + root.translate('DECLINE') + '</button>').appendTo(ribbon);
            var accept = $('<button class="accept '+root.vars.agreeclass+'">' + root.translate('AGREE') + '</button>').appendTo(ribbon);
            if ((root.vars.hasOwnProperty('policyurl') && root.vars.policyurl.length > 0) || (root.vars.lawlink && root.vars.lawlink.length > 0)) {
                var links = $('<ul class="links"/>').appendTo(message);
                if (root.vars.policyurl && root.vars.policyurl.length > 0) {
                    $('<li><a href="' + root.vars.policyurl + '" target="' + root.vars.policytarget + '">' + root.translate('POLICYTEXT') + '</a></li>').appendTo(links);
                }
                if (root.vars.lawlink && root.vars.lawlink.length > 0) {
                    $('<li><a href="' + root.vars.lawlink + '" target="_BLANK">' + root.translate('LAWLINK_TEXT') + '</a></li>').appendTo(links);
                }
            }
            $(decline).click(function () {
                root.declineCookies();
            });
            $(accept).click(function () {
                root.acceptCookies();
            });
        };
        var displayConfirm = function () {
            if (parseInt(root.getDataValue()) !== 1) {
                var r = confirm(root.translate('MESSAGE') + ' ' + root.translate('JSMESSAGE'));
                if (r === true) {
                    root.acceptCookies();
                } else {
                    root.declineCookies();
                }
            }
        };
        var displayModal = function () {
            if (parseInt(root.getDataValue()) !== 1) {
                if (!document.getElementById('eprivacyModal')) {
                    $(root.vars.modalmarkup).appendTo(document.body);
                    $('#eprivacyModal button.plg_system_eprivacy_agreed').click(function () {
                        root.acceptCookies();
                    });
                    $('#eprivacyModal button.plg_system_eprivacy_declined').click(function () {
                        root.declineCookies();
                    });
                }
                $('#eprivacyModal').modal('show');
            }
            ;
        };
        construct(options);
    };
    var npstorage = function () {
        var cache = (window.name[0] === '{' && window.name.substr(-1) === '}') ? JSON.parse(window.name) : {};
        this.get = function (key, dflt) {
            return cache.hasOwnProperty(key) ? cache[key] : dflt;
        };
        this.set = function (key, value) {
            if (typeof key === undefined && typeof value === undefined) {
                return;
            }
            cache[key] = value;
            window.name = JSON.stringify(cache);
        };
        this.unset = function (key) {
            if (typeof key === undefined) {
                return;
            }
            delete cache[key];
            window.name = JSON.stringify(cache);
        };
    };
    $(document).ready(function () {
        var options = Joomla.getOptions!==undefined?Joomla.getOptions('plg_system_eprivacy'):Joomla.optionsStorage.plg_system_eprivacy;
        window.eprivacy = new ePrivacyClass(options);
    });
})(jQuery);
(function () {
    var optionsElement = document.getElementsByClassName('joomla-script-options')[0];
    var options = optionsElement!==undefined?JSON.parse(optionsElement.innerText):Joomla.optionsStorage.plg_system_eprivacy;
    if (!options.plg_system_eprivacy.accepted) {
        if (!document.__defineGetter__) {
            if (navigator.appVersion.indexOf("MSIE 6.") === -1 || navigator.appVersion.indexOf("MSIE 7.") === -1) { // javascript cookies blocked only in IE8 and up
                Object.defineProperty(document, 'cookie', {
                    get: function () {
                        return '';
                    },
                    set: function () {
                        return true;
                    }
                });
            }
        } else { // non IE browsers use this method to block javascript cookies
            document.__defineGetter__("cookie", function () {
                return '';
            });
            document.__defineSetter__("cookie", function () {});
        }
        window.localStorage.clear();
        window.localStorage.__proto__ = Object.create(window.Storage.prototype);
        window.localStorage.__proto__.setItem = function () {
            return undefined;
        };
        window.sessionStorage.clear();
        window.sessionStorage.__proto__ = Object.create(window.Storage.prototype);
        window.sessionStorage.__proto__.setItem = function () {
            return undefined;
        };
    }
})();
// Polyfill for Object.assign in IE
if (typeof Object.assign !== 'function') {
    // Must be writable: true, enumerable: false, configurable: true
    Object.defineProperty(Object, "assign", {
        value: function assign(target, varArgs) { // .length of function is 2
            'use strict';
            if (target === null) { // TypeError if undefined or null
                throw new TypeError('Cannot convert undefined or null to object');
            }

            var to = Object(target);

            for (var index = 1; index < arguments.length; index++) {
                var nextSource = arguments[index];

                if (nextSource !== null) { // Skip over if undefined or null
                    for (var nextKey in nextSource) {
                        // Avoid bugs when hasOwnProperty is shadowed
                        if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
                            to[nextKey] = nextSource[nextKey];
                        }
                    }
                }
            }
            return to;
        },
        writable: true,
        configurable: true
    });
}
;