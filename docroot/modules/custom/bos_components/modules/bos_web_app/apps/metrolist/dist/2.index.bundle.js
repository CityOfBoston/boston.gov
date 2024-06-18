(window["webpackJsonp"] = window["webpackJsonp"] || []).push([[2],{

/***/ "./node_modules/postcss-loader/src/index.js!./node_modules/sass-loader/dist/cjs.js!./src/components/Range/Range.scss":
/*!******************************************************************************************************************!*\
  !*** ./node_modules/postcss-loader/src!./node_modules/sass-loader/dist/cjs.js!./src/components/Range/Range.scss ***!
  \******************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

module.exports = ".ml-range {\n  --track-width: 100%;\n  --track-height: 1.94444rem;\n  --thumb-diameter: 0.97222rem;\n  --thumb-radius: 0.48611rem;\n  --useful-width: calc( var(--track-width) - var(--thumb-diameter) );\n  --min-max-difference: calc( var(--max) - var(--min) );\n  --fill:\n    linear-gradient(\n      90deg,\n      red calc( var(--thumb-radius) + ( var(--lower-bound) - var(--min) ) / var(--min-max-difference) * var(--useful-width) ),\n      transparent 0\n    ),\n    linear-gradient(\n      90deg,\n      red calc( var(--thumb-radius) + ( var(--upper-bound) - var(--min) ) / var(--min-max-difference) * var(--useful-width) ),\n      transparent 0\n    )\n  ;\n  line-height: 0.85; }\n\n.ml-range__multi-input {\n  display: grid;\n  grid-template-rows: max-content var(--track-height) max-content;\n  grid-gap: .625rem;\n  position: relative;\n  padding-top: 0.25rem;\n  width: var(--track-width); }\n  .ml-range__multi-input * {\n    --highlighted: 0;\n    --not-highlighted: calc( 1 - var(--highlighted) );\n    margin: 0; }\n  .ml-range__multi-input::before, .ml-range__multi-input::after {\n    grid-column: 1;\n    grid-row: 2;\n    color: #eee;\n    content: ''; }\n  .ml-range__multi-input::before {\n    height: 35px;\n    border: 2px solid #383838; }\n  .ml-range__multi-input::after {\n    /* non-standard WebKit version */\n    -webkit-mask: var(--fill);\n    -webkit-mask-composite: xor;\n    /* standard version, supported in Firefox */\n    mask: var(--fill);\n    mask-composite: exclude;\n    position: relative;\n    top: .5rem;\n    height: 23px;\n    background: #51ACFF; }\n\n.ml-range__input {\n  padding: 0;\n  height: 2.2rem;\n  color: inherit;\n  border: none;\n  grid-column: 1;\n  grid-row: 2;\n  z-index: calc( 1 + var( --highlighted));\n  top: 0;\n  left: 0;\n  background: none;\n  /* get rid of white Chrome background */\n  cursor: grab;\n  pointer-events: none; }\n  .ml-range__input::-webkit-slider-runnable-track, .ml-range__input::-webkit-slider-thumb, .ml-range__input {\n    -webkit-appearance: none; }\n  .ml-range__input::-webkit-slider-runnable-track {\n    width: 100%;\n    height: 100%;\n    background: none; }\n  .ml-range__input::-moz-range-track {\n    width: 100%;\n    height: 100%;\n    background: none; }\n  .ml-range__input::-webkit-slider-thumb {\n    box-sizing: border-box;\n    /* different between Chrome & Firefox */\n    /* box-sizing needed now that we have a non-zero border */\n    pointer-events: auto;\n    width: 0.97222rem;\n    height: 0.97222rem;\n    border-radius: 50% 50% 50% 0;\n    transform: translateY(-0.72917rem) rotate(-45deg);\n    border: 8px solid #0A1F2F;\n    background-color: #0A1F2F; }\n    .ml-range__input::-webkit-slider-thumb:active, .ml-range__input::-webkit-slider-thumb:hover, .ml-range__input::-webkit-slider-thumb:focus {\n      border-color: #FB4D42;\n      background-color: #FB4D42; }\n  .ml-range__input:focus::-webkit-slider-thumb {\n    outline: inherit;\n    border-color: #FB4D42;\n    background-color: #FB4D42; }\n  .ml-range__input:first-of-type::-webkit-slider-thumb, .ml-range__input:last-of-type.ml-range__input--inverted::-webkit-slider-thumb {\n    transform: translate(0.07rem, -0.72917rem) rotate(-45deg); }\n  .ml-range__input:last-of-type::-webkit-slider-thumb, .ml-range__input:first-of-type.ml-range__input--inverted::-webkit-slider-thumb {\n    transform: translate(-0.07rem, -0.72917rem) rotate(-45deg); }\n  .ml-range__input::-moz-range-thumb {\n    box-sizing: border-box;\n    /* different between Chrome & Firefox */\n    /* box-sizing needed now that we have a non-zero border */\n    pointer-events: auto;\n    width: 0.97222rem;\n    height: 0.97222rem;\n    border-radius: 50% 50% 50% 0;\n    transform: translateY(-0.72917rem) rotate(-45deg);\n    border: 8px solid #0A1F2F;\n    background-color: #0A1F2F; }\n    .ml-range__input::-moz-range-thumb:active, .ml-range__input::-moz-range-thumb:hover, .ml-range__input::-moz-range-thumb:focus {\n      border-color: #FB4D42;\n      background-color: #FB4D42; }\n  .ml-range__input:focus::-moz-range-thumb {\n    outline: inherit;\n    border-color: #FB4D42;\n    background-color: #FB4D42; }\n  .ml-range__input:focus {\n    --highlighted: 1; }\n  .ml-range__input:active {\n    cursor: grabbing; }\n\n.ml-range__review {\n  display: inline-flex;\n  flex-direction: row;\n  justify-content: flex-start; }\n\n.ml-range__review > .en-dash {\n  margin: 0 .25rem; }\n\n.ml-range__review.ml-range__review--inverted {\n  flex-direction: row-reverse;\n  justify-content: flex-end; }\n\n.manual_range_container {\n  display: flex;\n  width: 100%;\n  justify-content: space-between;\n  align-items: center; }\n\n.ml-manual_range__input {\n  max-width: 40%;\n  width: 100%; }\n"

/***/ }),

/***/ "./src/components/Range/Range.scss":
/*!*****************************************!*\
  !*** ./src/components/Range/Range.scss ***!
  \*****************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var api = __webpack_require__(/*! ../../../node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js */ "./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js");
            var content = __webpack_require__(/*! !../../../node_modules/postcss-loader/src!../../../node_modules/sass-loader/dist/cjs.js!./Range.scss */ "./node_modules/postcss-loader/src/index.js!./node_modules/sass-loader/dist/cjs.js!./src/components/Range/Range.scss");

            content = content.__esModule ? content.default : content;

            if (typeof content === 'string') {
              content = [[module.i, content, '']];
            }

var options = {};

options.insert = "head";
options.singleton = false;

var update = api(content, options);



module.exports = content.locals || {};

/***/ })

}]);