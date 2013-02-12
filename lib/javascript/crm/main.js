(function() {
    var
    dropdownToggleHash = {};

    jQuery.extend({
        dropdownToggle: function(options) {
            // default options
            options = jQuery.extend({
                //switcherSelector: "#id" or ".class",          - button
                //dropdownID: "id",                             - drop panel
                //anchorSelector: "#id" or ".class",            - near field
                //noActiveSwitcherSelector: "#id" or ".class",  - dont hide
                addTop: 0,
                addLeft: 0,
                position: "absolute",
                fixWinSize: true,
                enableAutoHide: true,
                showFunction: null,
                hideFunction: null,
                alwaysUp: false
            }, options);

            var _toggle = function(switcherObj, dropdownID, addTop, addLeft, fixWinSize, position, anchorSelector, showFunction, alwaysUp) {
                fixWinSize = fixWinSize === true;
                addTop = addTop || 0;
                addLeft = addLeft || 0;
                position = position || "absolute";

                var targetPos = jQuery(anchorSelector || switcherObj).offset();
                var dropdownItem = jQuery("#" + dropdownID);

                var elemPosLeft = targetPos.left;
                var elemPosTop = targetPos.top + jQuery(anchorSelector || switcherObj).outerHeight();

                var w = jQuery(window);
                var topPadding = w.scrollTop();
                var leftPadding = w.scrollLeft();

                if (position == "fixed") {
                    addTop -= topPadding;
                    addLeft -= leftPadding;
                }

                var scrWidth = w.width();
                var scrHeight = w.height();

                if (fixWinSize
                    && (targetPos.left + addLeft + dropdownItem.outerWidth()) > (leftPadding + scrWidth))
                    elemPosLeft = Math.max(0, leftPadding + scrWidth - dropdownItem.outerWidth()) - addLeft;

                if (fixWinSize
                    && (elemPosTop + dropdownItem.outerHeight()) > (topPadding + scrHeight)
                    && (targetPos.top - dropdownItem.outerHeight()) > topPadding
                    || alwaysUp)
                    elemPosTop = targetPos.top - dropdownItem.outerHeight();

                dropdownItem.css(
                {
                    "position": position,
                    "top": elemPosTop + addTop,
                    "left": elemPosLeft + addLeft
                });
                if (typeof showFunction === "function")
                    showFunction(switcherObj, dropdownItem);

                dropdownItem.toggle();
            };

            var _registerAutoHide = function(event, switcherSelector, dropdownSelector, hideFunction) {
                if (jQuery(dropdownSelector).is(":visible")) {
                    var $targetElement = jQuery((event.target) ? event.target : event.srcElement);
                    if (!$targetElement.parents().andSelf().is(switcherSelector + ", " + dropdownSelector)) {
                        if (typeof hideFunction === "function")
                            hideFunction($targetElement);
                        jQuery(dropdownSelector).hide();
                    }
                }
            };

            if (options.switcherSelector && options.dropdownID) {
                var toggleFunc = function(e) {
                    _toggle(jQuery(this), options.dropdownID, options.addTop, options.addLeft, options.fixWinSize, options.position, options.anchorSelector, options.showFunction, options.alwaysUp);
                };
                if (!dropdownToggleHash.hasOwnProperty(options.switcherSelector + options.dropdownID)) {
                    jQuery(options.switcherSelector).live("click", toggleFunc);
                    dropdownToggleHash[options.switcherSelector + options.dropdownID] = true;
                }
            }

            if (options.enableAutoHide && options.dropdownID) {
                var hideFunc = function(e) {
                    var allSwitcherSelectors = options.noActiveSwitcherSelector ?
                    options.switcherSelector + ", " + options.noActiveSwitcherSelector : options.switcherSelector;
                    _registerAutoHide(e, allSwitcherSelectors, "#" + options.dropdownID, options.hideFunction);

                };
                jQuery(document).unbind("click", hideFunc);
                jQuery(document).bind("click", hideFunc);
            }

            return {
                toggle: _toggle,
                registerAutoHide: _registerAutoHide
            };
        }
    });
})();
function setTableRow(tableId) {
    count = 0;
    $J('table#'+tableId+' tbody tr:visible').each(function(){
        $J(this).removeClass("row1 row2");
        rowClass = (count%2 == 0) ? "row1" : "row2";
        $J(this).addClass(rowClass);
        count++;
    });
}
$J(function(){
    
    $J('form#searchcustomer #term').autocomplete({
            minLength: 2,
            source: function( request, response ) {
                $J.ajax({
                    url         : 'index.php?cmd=crm&act=customersearch',
                    type        : "POST",
                    data        : $J('form#searchcustomer').serialize(),
                    dataType    : 'json',
                    success: function(data) {
                        response( data );
                    }
                });
            }
        });
});

