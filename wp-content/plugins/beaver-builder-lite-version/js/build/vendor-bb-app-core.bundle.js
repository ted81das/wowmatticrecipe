/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@beaverbuilder/app-core/dist/index.es.js":
/*!***************************************************************!*\
  !*** ./node_modules/@beaverbuilder/app-core/dist/index.es.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   App: () => (/* binding */ de),
/* harmony export */   Error: () => (/* binding */ re),
/* harmony export */   Root: () => (/* binding */ ce),
/* harmony export */   createAppState: () => (/* binding */ D),
/* harmony export */   createStoreRegistry: () => (/* binding */ F)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var redux__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! redux */ "redux");
/* harmony import */ var redux__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(redux__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-router-dom */ "react-router-dom");
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_router_dom__WEBPACK_IMPORTED_MODULE_2__);
function P(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function E(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function S(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?E(Object(r),!0).forEach((function(t){P(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):E(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}var A={handle:"",label:"",render:function(){return null},icon:function(){return null},isEnabled:!0},D=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:A;return{reducers:{apps:function(){var t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},r=arguments.length>1?arguments[1]:void 0;switch(r.type){case"REGISTER_APP":return S(P({},r.handle,S(S({},e),{},{handle:r.handle},r.config)),t);case"UNREGISTER_APP":return delete t[r.handle],S({},t);default:return t}}},actions:{registerApp:function(){return{type:"REGISTER_APP",handle:arguments.length>0&&void 0!==arguments[0]?arguments[0]:"",config:arguments.length>1&&void 0!==arguments[1]?arguments[1]:{}}},unregisterApp:function(){return{type:"UNREGISTER_APP",handle:arguments.length>0&&void 0!==arguments[0]?arguments[0]:""}}}}};function C(e,t){(null==t||t>e.length)&&(t=e.length);for(var r=0,n=new Array(t);r<t;r++)n[r]=e[r];return n}function _(e,t){return function(e){if(Array.isArray(e))return e}(e)||function(e,t){var r=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=r){var n,o,c=[],i=!0,a=!1;try{for(r=r.call(e);!(i=(n=r.next()).done)&&(c.push(n.value),!t||c.length!==t);i=!0);}catch(e){a=!0,o=e}finally{try{i||null==r.return||r.return()}finally{if(a)throw o}}return c}}(e,t)||function(e,t){if(e){if("string"==typeof e)return C(e,t);var r=Object.prototype.toString.call(e).slice(8,-1);return"Object"===r&&e.constructor&&(r=e.constructor.name),"Map"===r||"Set"===r?Array.from(e):"Arguments"===r||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(r)?C(e,t):void 0}}(e,t)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function x(e){return x="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},x(e)}var R=function(e,t){return x(e)===x(t)&&("string"==typeof e||"number"==typeof e?e===t:JSON.stringify(e)===JSON.stringify(t))},k=function(e,t,r){return"boolean"==typeof e?e:"function"==typeof e?e(t,r):"string"==typeof e?!R(t[e],r[e]):!!Array.isArray(e)&&e.some((function(e){return!R(t[e],r[e])}))};function T(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function U(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?T(Object(r),!0).forEach((function(t){P(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):T(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}var I={exports:{}};const L=(e,t)=>{if("string"!=typeof e&&!Array.isArray(e))throw new TypeError("Expected the input to be `string | string[]`");t=Object.assign({pascalCase:!1},t);if(e=Array.isArray(e)?e.map((e=>e.trim())).filter((e=>e.length)).join("-"):e.trim(),0===e.length)return"";if(1===e.length)return t.pascalCase?e.toUpperCase():e.toLowerCase();return e!==e.toLowerCase()&&(e=(e=>{let t=!1,r=!1,n=!1;for(let o=0;o<e.length;o++){const c=e[o];t&&/[a-zA-Z]/.test(c)&&c.toUpperCase()===c?(e=e.slice(0,o)+"-"+e.slice(o),t=!1,n=r,r=!0,o++):r&&n&&/[a-zA-Z]/.test(c)&&c.toLowerCase()===c?(e=e.slice(0,o-1)+"-"+e.slice(o-1),n=r,r=!1,t=!0):(t=c.toLowerCase()===c&&c.toUpperCase()!==c,n=r,r=c.toUpperCase()===c&&c.toLowerCase()!==c)}return e})(e)),e=e.replace(/^[_.\- ]+/,"").toLowerCase().replace(/[_.\- ]+(\w|$)/g,((e,t)=>t.toUpperCase())).replace(/\d+(\w|$)/g,(e=>e.toUpperCase())),r=e,t.pascalCase?r.charAt(0).toUpperCase()+r.slice(1):r;var r};I.exports=L,I.exports.default=L;var N=I.exports,B=function(e,t,r){return Object.entries(r).map((function(r){var n=_(r,1)[0];if(!t[n]){var o="SET_".concat(n.toUpperCase()),c=N("set_".concat(n));e[c]=function(e){return{type:o,value:e}}}})),e},H=function(e,t){return Object.keys(e).length||Object.keys(t).length?(Object.entries(t).map((function(t){var r=_(t,2),n=r[0],o=r[1];e[n]||(e[n]=function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:o,t=arguments.length>1?arguments[1]:void 0;return t.type==="SET_".concat(n.toUpperCase())?t.value:e})})),(0,redux__WEBPACK_IMPORTED_MODULE_1__.combineReducers)(e)):function(e){return e}},M=function(e,t){var r="undefined"==typeof window?null:window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__;return(r?r({name:e}):redux__WEBPACK_IMPORTED_MODULE_1__.compose)((0,redux__WEBPACK_IMPORTED_MODULE_1__.applyMiddleware)(G(t)))},G=function(e){var t=e.before,r=e.after;return function(e){return function(n){return function(o){t&&t[o.type]&&t[o.type](o,e);var c=n(o);return r&&r[o.type]&&r[o.type](o,e),c}}}},J=function(e){return e.charAt(0).toUpperCase()+e.slice(1)};function z(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function $(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?z(Object(r),!0).forEach((function(t){P(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):z(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}var F=function(){var e={};return{registerStore:function(t,r){var n=r.state,o=void 0===n?{}:n,c=r.cache,i=void 0===c?[]:c,a=r.actions,u=void 0===a?{}:a,l=r.reducers,f=void 0===l?{}:l,s=r.selectors,p=void 0===s?{}:s,y=r.effects,v=void 0===y?{}:y;if(!t)throw new Error("Missing key required for registerStore.");if(e[t])throw new Error("A store with the key '".concat(t,"' already exists."));var g,O,d,h=function(e,t,r){if(r.length&&"undefined"!=typeof localStorage){var n=localStorage.getItem(e);if(n){var o=JSON.parse(n),c={};return r.map((function(e){o[e]&&(c[e]=o[e])})),U(U({},t),c)}}return t}(t,o,i);e[t]={actions:B($({},u),f,h),store:(0,redux__WEBPACK_IMPORTED_MODULE_1__.createStore)(H($({},f),h),h,M(t,v))},e[t].selectors=function(e,t){var r={},n=t.getState();return Object.entries(n).map((function(t){var r=_(t,1)[0],n=N("get_".concat(r));e[n]||(e[n]=function(e){return e[r]})})),Object.entries(e).map((function(e){var n=_(e,2),o=n[0],c=n[1];r[o]=function(){for(var e=arguments.length,r=new Array(e),n=0;n<e;n++)r[n]=arguments[n];return c.apply(void 0,[t.getState()].concat(r))}})),r}($({},p),e[t].store),g=t,O=e[t].store,(d=i).length&&"undefined"!=typeof localStorage&&O.subscribe((function(){var e=O.getState(),t={};d.map((function(r){t[r]=e[r]})),localStorage.setItem(g,JSON.stringify(t))}))},useStore:function(n){var c=!(arguments.length>1&&void 0!==arguments[1])||arguments[1],i=e[n].store,a=i.getState(),u=(0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(a),l=_((0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(a),2),f=l[0],s=l[1];return (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)((function(){return s(i.getState()),i.subscribe((function(){var e=i.getState();k(c,u.current,e)&&s($({},e)),u.current=e}))}),[]),f},getStore:function(t){return e[t].store},getDispatch:function(t){var r=e[t],n=r.actions,o=r.store,c={};return Object.entries(n).map((function(e){var t=_(e,2),r=t[0],n=t[1];c[r]=function(){for(var e=arguments.length,t=new Array(e),r=0;r<e;r++)t[r]=arguments[r];return new Promise((function(e){e(o.dispatch(n.apply(void 0,t)))}))}})),c},getSelectors:function(t){return e[t].selectors},getHooks:function(o){var c=e[o],i=c.actions,a=c.store;return function(e,o){var c=e.getState(),i={};return Object.keys(c).map((function(c){var a="use".concat(J(c));i[a]=function(){var i=!(arguments.length>0&&void 0!==arguments[0])||arguments[0],a=_((0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(e.getState()[c]),2),u=a[0],l=a[1],f=(0,react__WEBPACK_IMPORTED_MODULE_0__.useRef)(e.getState()[c]);(0,react__WEBPACK_IMPORTED_MODULE_0__.useLayoutEffect)((function(){return l(e.getState()[c]),f.current=e.getState()[c],e.subscribe((function(){var t=e.getState();k(i,u,f.current)&&l(t[c]),f.current=t[c]}))}),[]);var s="set".concat(J(c)),p=o[s];return[u,p]}})),i}(a,(0,redux__WEBPACK_IMPORTED_MODULE_1__.bindActionCreators)(i,a.dispatch))}}};function X(){return X=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},X.apply(this,arguments)}function Z(e,t){if(null==e)return{};var r,n,o=function(e,t){if(null==e)return{};var r,n,o={},c=Object.keys(e);for(n=0;n<c.length;n++)r=c[n],t.indexOf(r)>=0||(o[r]=e[r]);return o}(e,t);if(Object.getOwnPropertySymbols){var c=Object.getOwnPropertySymbols(e);for(n=0;n<c.length;n++)r=c[n],t.indexOf(r)>=0||Object.prototype.propertyIsEnumerable.call(e,r)&&(o[r]=e[r])}return o}function q(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function V(e,t){return V=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e},V(e,t)}function Y(e,t){if(t&&("object"===x(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}function K(e){return K=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)},K(e)}var Q=["error","title","children","style"];function W(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function ee(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?W(Object(r),!0).forEach((function(t){P(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):W(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}function te(e){var t=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch(e){return!1}}();return function(){var r,n=K(e);if(t){var o=K(this).constructor;r=Reflect.construct(n,arguments,o)}else r=n.apply(this,arguments);return Y(this,r)}}var re={},ne=function(e){!function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&V(e,t)}(a,react__WEBPACK_IMPORTED_MODULE_0__.Component);var t,r,n,o=te(a);function a(e){var t;return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,a),(t=o.call(this,e)).state={hasError:!1,error:null},t}return t=a,n=[{key:"getDerivedStateFromError",value:function(e){return{hasError:!0,error:e}}}],(r=[{key:"render",value:function(){var e=this.props,t=e.alternate,r=void 0===t?oe:t,n=e.children,o=this.state,i=o.hasError,a=o.error;return i?(0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(r,{error:a}):n}}])&&q(t.prototype,r),n&&q(t,n),Object.defineProperty(t,"prototype",{writable:!1}),a}(),oe=function(t){var r=t.error,n=t.title,o=void 0===n?"There seems to be an error":n,c=t.children,i=t.style,a=void 0===i?{}:i,u=Z(t,Q),l=ee(ee({},a),{},{display:"flex",flexDirection:"column",flex:"1 1 auto",justifyContent:"center",alignItems:"center",padding:40,textAlign:"center",minHeight:0,maxHeight:"100%"});return react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div",X({style:l},u),react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h1",{style:{marginBottom:20}},o),react__WEBPACK_IMPORTED_MODULE_0___default().createElement("code",{style:{padding:10}},r.message),c)};re.Boundary=ne,re.Page=oe;var ce=function(t){var r=t.children,n=t.error,o=t.errorBoundaryProps,c=void 0===o?{}:o,i=t.router,a=void 0===i?react_router_dom__WEBPACK_IMPORTED_MODULE_2__.MemoryRouter:i,u=t.routerProps,l=void 0===u?{}:u;return react__WEBPACK_IMPORTED_MODULE_0___default().createElement(re.Boundary,X({alternate:n},c),react__WEBPACK_IMPORTED_MODULE_0___default().createElement(a,l,r))},ie={handle:null,label:null,isAppRoot:!1},ae=(0,react__WEBPACK_IMPORTED_MODULE_0__.createContext)(ie),ue=function(){return (0,react__WEBPACK_IMPORTED_MODULE_0__.useContext)(ae)},le=["root"];function fe(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function se(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?fe(Object(r),!0).forEach((function(t){P(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):fe(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}var pe=function(){return react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h1",{style:{margin:"auto"}},"Loading...")},ye=function(t){var r=t.loading,n=t.error,c=void 0===n?ge:n,i=t.apps,a=(0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useHistory)(),u=(0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useLocation)(),f=(0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useParams)().app;if((0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)((function(){if(i[f]&&"function"==typeof i[f].onMount)return i[f].onMount()}),[f]),!i[f])return a.go(-a.length),a.replace("/",{}),null;var s=i[f],p=s.label,y=void 0===p?"":p,b=s.root,v=s.lazyLoad,g=2>=u.pathname.split("/").length,d=se(se({},ie),{},{handle:f,baseURL:"/".concat(f),label:y,isAppRoot:g}),h=function(){return void 0===v||v};return react__WEBPACK_IMPORTED_MODULE_0___default().createElement(ae.Provider,{value:d},react__WEBPACK_IMPORTED_MODULE_0___default().createElement(re.Boundary,{alternate:c,key:f},h()&&react__WEBPACK_IMPORTED_MODULE_0___default().createElement(react__WEBPACK_IMPORTED_MODULE_0__.Suspense,{fallback:react__WEBPACK_IMPORTED_MODULE_0___default().createElement(r,null)},react__WEBPACK_IMPORTED_MODULE_0___default().createElement(ve,X({root:b},d))),!h()&&react__WEBPACK_IMPORTED_MODULE_0___default().createElement(ve,X({root:b},d))))},be=function(){return react__WEBPACK_IMPORTED_MODULE_0___default().createElement(Oe,null,react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h1",null,"App Not Found"))},ve=(0,react__WEBPACK_IMPORTED_MODULE_0__.memo)((function(t){var r=t.root,n=Z(t,le);return r?react__WEBPACK_IMPORTED_MODULE_0___default().createElement(r,n):react__WEBPACK_IMPORTED_MODULE_0___default().createElement(be,null)})),ge=function(t){var r=ue().label;return react__WEBPACK_IMPORTED_MODULE_0___default().createElement(re.Page,X({title:"There seems to be an issue with the ".concat(r," app.")},t))},Oe=function(t){var r=t.children;return react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div",{style:{flex:"1 1 auto",minHeight:0,maxHeight:"100%",display:"flex",flexDirection:"column",justifyContent:"center",alignItems:"center"}},r)},de={};de.use=ue,de.Content=function(t){var r=t.apps,n=void 0===r?{}:r,o=t.defaultApp,c=void 0===o?"home":o,i=t.loading,a=void 0===i?pe:i,u=(0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useLocation)().search;return Object.keys(n).length?react__WEBPACK_IMPORTED_MODULE_0___default().createElement(react_router_dom__WEBPACK_IMPORTED_MODULE_2__.Switch,null,c&&react__WEBPACK_IMPORTED_MODULE_0___default().createElement(react_router_dom__WEBPACK_IMPORTED_MODULE_2__.Route,{exact:!0,path:"/"},react__WEBPACK_IMPORTED_MODULE_0___default().createElement(react_router_dom__WEBPACK_IMPORTED_MODULE_2__.Redirect,{to:"/".concat(c).concat(u)})),react__WEBPACK_IMPORTED_MODULE_0___default().createElement(react_router_dom__WEBPACK_IMPORTED_MODULE_2__.Route,{path:"/:app",render:function(){return react__WEBPACK_IMPORTED_MODULE_0___default().createElement(ye,{loading:a,apps:n})}})):(console.warn("App Core: You have no apps."),null)};



/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = React;

/***/ }),

/***/ "react-router-dom":
/*!*********************************!*\
  !*** external "ReactRouterDOM" ***!
  \*********************************/
/***/ ((module) => {

module.exports = ReactRouterDOM;

/***/ }),

/***/ "redux":
/*!************************!*\
  !*** external "Redux" ***!
  \************************/
/***/ ((module) => {

module.exports = Redux;

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!************************************!*\
  !*** ./src/vendors/bb-app-core.js ***!
  \************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var vendor_app_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vendor-app-core */ "./node_modules/@beaverbuilder/app-core/dist/index.es.js");

window.FL = window.FL || {};
FL.vendors = FL.vendors || {};
FL.vendors.BBAppCore = vendor_app_core__WEBPACK_IMPORTED_MODULE_0__;
})();

/******/ })()
;
