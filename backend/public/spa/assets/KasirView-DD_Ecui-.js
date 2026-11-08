import{G as he,E as pe,U as je,o as c,h as Z,w as H,c as k,H as q,a as $,V as Ie,W as Le,I as G,F as O,b as t,Q as Y,t as _,i as L,X as De,Y as Ke,Z as xe,n as W,$ as Pe,J as ke,K as Ee,a0 as Me,a1 as qe,a2 as Ve,a3 as le,a4 as Re,_ as ee,a5 as be,a6 as se,u as b,C as V,j as de,a7 as ae,r as I,a8 as ye,a9 as Te,aa as Ae,D as z,y as J,P as Oe,ab as ze,f as ge,O as Ue,q as Ne,S as He,A as F,g as N,ac as Q,B as we,ad as Fe,M as Ze,N as Ge,ae as We,p as Je}from"./index-BndcKvZR.js";import{B as Ye}from"./BayarDialog-nuXQQcN7.js";import{s as _e}from"./index-p63i_50B.js";import{s as Qe}from"./index-xn9oFe18.js";import{a as Xe,s as en}from"./index-CVphUtZh.js";import{s as nn}from"./index-H_T6QpVL.js";import{P as tn}from"./PageHeader-CjsmnPxK.js";var an=`
    .p-drawer {
        display: flex;
        flex-direction: column;
        transform: translate3d(0px, 0px, 0px);
        position: relative;
        transition: transform 0.3s;
        background: dt('drawer.background');
        color: dt('drawer.color');
        border-style: solid;
        border-color: dt('drawer.border.color');
        box-shadow: dt('drawer.shadow');
    }

    .p-drawer-content {
        overflow-y: auto;
        flex-grow: 1;
        padding: dt('drawer.content.padding');
    }

    .p-drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        padding: dt('drawer.header.padding');
    }

    .p-drawer-footer {
        padding: dt('drawer.footer.padding');
    }

    .p-drawer-title {
        font-weight: dt('drawer.title.font.weight');
        font-size: dt('drawer.title.font.size');
    }

    .p-drawer-full .p-drawer {
        transition: none;
        transform: none;
        width: 100vw !important;
        height: 100vh !important;
        max-height: 100%;
        top: 0px !important;
        left: 0px !important;
        border-width: 1px;
    }

    .p-drawer-left .p-drawer-enter-active {
        animation: p-animate-drawer-enter-left 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .p-drawer-left .p-drawer-leave-active {
        animation: p-animate-drawer-leave-left 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }

    .p-drawer-right .p-drawer-enter-active {
        animation: p-animate-drawer-enter-right 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .p-drawer-right .p-drawer-leave-active {
        animation: p-animate-drawer-leave-right 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }

    .p-drawer-top .p-drawer-enter-active {
        animation: p-animate-drawer-enter-top 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .p-drawer-top .p-drawer-leave-active {
        animation: p-animate-drawer-leave-top 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }

    .p-drawer-bottom .p-drawer-enter-active {
        animation: p-animate-drawer-enter-bottom 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .p-drawer-bottom .p-drawer-leave-active {
        animation: p-animate-drawer-leave-bottom 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }

    .p-drawer-full .p-drawer-enter-active {
        animation: p-animate-drawer-enter-full 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    .p-drawer-full .p-drawer-leave-active {
        animation: p-animate-drawer-leave-full 0.5s cubic-bezier(0.32, 0.72, 0, 1);
    }
    
    .p-drawer-left .p-drawer {
        width: 20rem;
        height: 100%;
        border-inline-end-width: 1px;
    }

    .p-drawer-right .p-drawer {
        width: 20rem;
        height: 100%;
        border-inline-start-width: 1px;
    }

    .p-drawer-top .p-drawer {
        height: 10rem;
        width: 100%;
        border-block-end-width: 1px;
    }

    .p-drawer-bottom .p-drawer {
        height: 10rem;
        width: 100%;
        border-block-start-width: 1px;
    }

    .p-drawer-left .p-drawer-content,
    .p-drawer-right .p-drawer-content,
    .p-drawer-top .p-drawer-content,
    .p-drawer-bottom .p-drawer-content {
        width: 100%;
        height: 100%;
    }

    .p-drawer-open {
        display: flex;
    }

    .p-drawer-mask:dir(rtl) {
        flex-direction: row-reverse;
    }

    @keyframes p-animate-drawer-enter-left {
        from {
            transform: translate3d(-100%, 0px, 0px);
        }
    }

    @keyframes p-animate-drawer-leave-left {
        to {
            transform: translate3d(-100%, 0px, 0px);
        }
    }

    @keyframes p-animate-drawer-enter-right {
        from {
            transform: translate3d(100%, 0px, 0px);
        }
    }

    @keyframes p-animate-drawer-leave-right {
        to {
            transform: translate3d(100%, 0px, 0px);
        }
    }

    @keyframes p-animate-drawer-enter-top {
        from {
            transform: translate3d(0px, -100%, 0px);
        }
    }

    @keyframes p-animate-drawer-leave-top {
        to {
            transform: translate3d(0px, -100%, 0px);
        }
    }

    @keyframes p-animate-drawer-enter-bottom {
        from {
            transform: translate3d(0px, 100%, 0px);
        }
    }

    @keyframes p-animate-drawer-leave-bottom {
        to {
            transform: translate3d(0px, 100%, 0px);
        }
    }

    @keyframes p-animate-drawer-enter-full {
        from {
            opacity: 0;
            transform: scale(0.93);
        }
    }

    @keyframes p-animate-drawer-leave-full {
        to {
            opacity: 0;
            transform: scale(0.93);
        }
    }
`,sn={mask:function(n){var s=n.position,d=n.modal;return{position:"fixed",height:"100%",width:"100%",left:0,top:0,display:"flex",justifyContent:s==="left"?"flex-start":s==="right"?"flex-end":"center",alignItems:s==="top"?"flex-start":s==="bottom"?"flex-end":"center",pointerEvents:d?"auto":"none"}},root:{pointerEvents:"auto"}},rn={mask:function(n){var s=n.instance,d=n.props,f=["left","right","top","bottom"],o=f.find(function(C){return C===d.position});return["p-drawer-mask",{"p-overlay-mask p-overlay-mask-enter-active":d.modal,"p-drawer-open":s.containerVisible,"p-drawer-full":s.fullScreen},o?"p-drawer-".concat(o):""]},root:function(n){var s=n.instance;return["p-drawer p-component",{"p-drawer-full":s.fullScreen}]},header:"p-drawer-header",title:"p-drawer-title",pcCloseButton:"p-drawer-close-button",content:"p-drawer-content",footer:"p-drawer-footer"},on=he.extend({name:"drawer",style:an,classes:rn,inlineStyles:sn}),ln={name:"BaseDrawer",extends:ke,props:{visible:{type:Boolean,default:!1},position:{type:String,default:"left"},header:{type:null,default:null},baseZIndex:{type:Number,default:0},autoZIndex:{type:Boolean,default:!0},dismissable:{type:Boolean,default:!0},showCloseIcon:{type:Boolean,default:!0},closeButtonProps:{type:Object,default:function(){return{severity:"secondary",text:!0,rounded:!0}}},closeIcon:{type:String,default:void 0},modal:{type:Boolean,default:!0},blockScroll:{type:Boolean,default:!1},closeOnEscape:{type:Boolean,default:!0}},style:on,provide:function(){return{$pcDrawer:this,$parentInstance:this}}};function X(e){"@babel/helpers - typeof";return X=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(n){return typeof n}:function(n){return n&&typeof Symbol=="function"&&n.constructor===Symbol&&n!==Symbol.prototype?"symbol":typeof n},X(e)}function ue(e,n,s){return(n=un(n))in e?Object.defineProperty(e,n,{value:s,enumerable:!0,configurable:!0,writable:!0}):e[n]=s,e}function un(e){var n=dn(e,"string");return X(n)=="symbol"?n:n+""}function dn(e,n){if(X(e)!="object"||!e)return e;var s=e[Symbol.toPrimitive];if(s!==void 0){var d=s.call(e,n);if(X(d)!="object")return d;throw new TypeError("@@toPrimitive must return a primitive value.")}return(n==="string"?String:Number)(e)}var $e={name:"Drawer",extends:ln,inheritAttrs:!1,emits:["update:visible","show","after-show","hide","after-hide","before-hide"],data:function(){return{containerVisible:this.visible}},container:null,mask:null,content:null,headerContainer:null,footerContainer:null,closeButton:null,outsideClickListener:null,documentKeydownListener:null,watch:{dismissable:function(n){n&&!this.modal?this.bindOutsideClickListener():this.unbindOutsideClickListener()}},updated:function(){this.visible&&(this.containerVisible=this.visible)},beforeUnmount:function(){this.disableDocumentSettings(),this.mask&&this.autoZIndex&&le.clear(this.mask),this.container=null,this.mask=null},methods:{hide:function(){this.$emit("update:visible",!1)},onEnter:function(){this.$emit("show"),this.focus(),this.bindDocumentKeyDownListener(),this.autoZIndex&&le.set("modal",this.mask,this.baseZIndex||this.$primevue.config.zIndex.modal)},onAfterEnter:function(){this.enableDocumentSettings(),this.$emit("after-show")},onBeforeLeave:function(){this.modal&&!this.isUnstyled&&Re(this.mask,"p-overlay-mask-leave-active"),this.$emit("before-hide")},onLeave:function(){this.$emit("hide")},onAfterLeave:function(){this.autoZIndex&&le.clear(this.mask),this.unbindDocumentKeyDownListener(),this.containerVisible=!1,this.disableDocumentSettings(),this.$emit("after-hide")},onMaskClick:function(n){this.dismissable&&this.modal&&this.mask===n.target&&this.hide()},focus:function(){var n=function(f){return f&&f.querySelector("[autofocus]")},s=this.$slots.header&&n(this.headerContainer);s||(s=this.$slots.default&&n(this.container),s||(s=this.$slots.footer&&n(this.footerContainer),s||(s=this.closeButton))),s&&Ve(s)},enableDocumentSettings:function(){this.dismissable&&!this.modal&&this.bindOutsideClickListener(),this.blockScroll&&qe()},disableDocumentSettings:function(){this.unbindOutsideClickListener(),this.blockScroll&&Me()},onKeydown:function(n){n.code==="Escape"&&this.closeOnEscape&&this.hide()},containerRef:function(n){this.container=n},maskRef:function(n){this.mask=n},contentRef:function(n){this.content=n},headerContainerRef:function(n){this.headerContainer=n},footerContainerRef:function(n){this.footerContainer=n},closeButtonRef:function(n){this.closeButton=n?n.$el:void 0},bindDocumentKeyDownListener:function(){this.documentKeydownListener||(this.documentKeydownListener=this.onKeydown,document.addEventListener("keydown",this.documentKeydownListener))},unbindDocumentKeyDownListener:function(){this.documentKeydownListener&&(document.removeEventListener("keydown",this.documentKeydownListener),this.documentKeydownListener=null)},bindOutsideClickListener:function(){var n=this;this.outsideClickListener||(this.outsideClickListener=function(s){n.isOutsideClicked(s)&&n.hide()},document.addEventListener("click",this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&(document.removeEventListener("click",this.outsideClickListener,!0),this.outsideClickListener=null)},isOutsideClicked:function(n){return this.container&&!this.container.contains(n.target)}},computed:{fullScreen:function(){return this.position==="full"},closeAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.close:void 0},dataP:function(){return Ee(ue(ue(ue({"full-screen":this.position==="full"},this.position,this.position),"open",this.containerVisible),"modal",this.modal))}},directives:{focustrap:Pe},components:{Button:W,Portal:xe,TimesIcon:Ke}},cn=["data-p"],pn=["role","aria-modal","data-p"];function mn(e,n,s,d,f,o){var C=pe("Button"),l=pe("Portal"),i=je("focustrap");return c(),Z(l,null,{default:H(function(){return[f.containerVisible?(c(),k("div",q({key:0,ref:o.maskRef,onMousedown:n[0]||(n[0]=function(){return o.onMaskClick&&o.onMaskClick.apply(o,arguments)}),class:e.cx("mask"),style:e.sx("mask",!0,{position:e.position,modal:e.modal}),"data-p":o.dataP},e.ptm("mask")),[$(Ie,q({name:"p-drawer",onEnter:o.onEnter,onAfterEnter:o.onAfterEnter,onBeforeLeave:o.onBeforeLeave,onLeave:o.onLeave,onAfterLeave:o.onAfterLeave,appear:""},e.ptm("transition")),{default:H(function(){return[e.visible?Le((c(),k("div",q({key:0,ref:o.containerRef,class:e.cx("root"),style:e.sx("root"),role:e.modal?"dialog":"complementary","aria-modal":e.modal?!0:void 0,"data-p":o.dataP},e.ptmi("root")),[e.$slots.container?G(e.$slots,"container",{key:0,closeCallback:o.hide}):(c(),k(O,{key:1},[t("div",q({ref:o.headerContainerRef,class:e.cx("header")},e.ptm("header")),[G(e.$slots,"header",{class:Y(e.cx("title"))},function(){return[e.header?(c(),k("div",q({key:0,class:e.cx("title")},e.ptm("title")),_(e.header),17)):L("",!0)]}),e.showCloseIcon?G(e.$slots,"closebutton",{key:0,closeCallback:o.hide},function(){return[$(C,q({ref:o.closeButtonRef,type:"button",class:e.cx("pcCloseButton"),"aria-label":o.closeAriaLabel,unstyled:e.unstyled,onClick:o.hide},e.closeButtonProps,{pt:e.ptm("pcCloseButton"),"data-pc-group-section":"iconcontainer"}),{icon:H(function(g){return[G(e.$slots,"closeicon",{},function(){return[(c(),Z(De(e.closeIcon?"span":"TimesIcon"),q({class:[e.closeIcon,g.class]},e.ptm("pcCloseButton").icon),null,16,["class"]))]})]}),_:3},16,["class","aria-label","unstyled","onClick","pt"])]}):L("",!0)],16),t("div",q({ref:o.contentRef,class:e.cx("content")},e.ptm("content")),[G(e.$slots,"default")],16),e.$slots.footer?(c(),k("div",q({key:0,ref:o.footerContainerRef,class:e.cx("footer")},e.ptm("footer")),[G(e.$slots,"footer")],16)):L("",!0)],64))],16,pn)),[[i]]):L("",!0)]}),_:3},16,["onEnter","onAfterEnter","onBeforeLeave","onLeave","onAfterLeave"])],16,cn)):L("",!0)]}),_:3})}$e.render=mn;const fn={class:"muted info"},vn={class:"field"},hn={for:"e-qty"},kn={class:"field"},bn={class:"hint"},yn={class:"actions"},gn={__name:"EditBarisDialog",props:ae({baris:Object},{visible:{},visibleModifiers:{}}),emits:ae(["simpan","hapus"],["update:visible"]),setup(e,{emit:n}){const s=e,d=be(e,"visible"),f=n,o=I(1),C=I(0);se(()=>s.baris,i=>{i&&(o.value=i.qty,C.value=i.diskon)});const l=()=>{var i;return Math.round((o.value||0)*(((i=s.baris)==null?void 0:i.harga)||0))};return(i,g)=>{var K;const P=_e,D=W,v=ye;return c(),Z(v,{visible:d.value,"onUpdate:visible":g[4]||(g[4]=B=>d.value=B),modal:"",header:(K=e.baris)==null?void 0:K.nama,style:{width:"24rem"},breakpoints:{"600px":"94vw"}},{default:H(()=>[e.baris?(c(),k("form",{key:0,class:"form",onSubmit:g[3]||(g[3]=de(B=>(f("simpan",e.baris,{qty:o.value,diskon:C.value||0}),d.value=!1),["prevent"]))},[t("p",fn,_(b(V)(e.baris.harga))+" per "+_(e.baris.satuan),1),t("div",vn,[t("label",hn,"Jumlah ("+_(e.baris.satuan)+")",1),$(P,{modelValue:o.value,"onUpdate:modelValue":g[0]||(g[0]=B=>o.value=B),"input-id":"e-qty",min:0,"max-fraction-digits":2,locale:"id-ID",fluid:"",autofocus:""},null,8,["modelValue"])]),t("div",kn,[g[5]||(g[5]=t("label",{for:"e-diskon"},"Potongan harga",-1)),$(P,{modelValue:C.value,"onUpdate:modelValue":g[1]||(g[1]=B=>C.value=B),"input-id":"e-diskon",mode:"currency",currency:"IDR",locale:"id-ID","max-fraction-digits":0,min:0,max:l(),fluid:""},null,8,["modelValue","max"]),t("small",bn,"Mengurangi harga baris ini. Subtotal: "+_(b(V)(l()-(C.value||0))),1)]),t("div",yn,[$(D,{type:"button",label:"Hapus Barang",icon:"pi pi-trash",severity:"danger",text:"",onClick:g[2]||(g[2]=B=>(f("hapus",e.baris),d.value=!1))}),$(D,{type:"submit",label:"Simpan",icon:"pi pi-check",disabled:!o.value},null,8,["disabled"])])],32)):L("",!0)]),_:1},8,["visible","header"])}}},wn=ee(gn,[["__scopeId","data-v-d07c389a"]]);var _n=`
    .p-progressspinner {
        position: relative;
        margin: 0 auto;
        width: 100px;
        height: 100px;
        display: inline-block;
    }

    .p-progressspinner::before {
        content: '';
        display: block;
        padding-top: 100%;
    }

    .p-progressspinner-spin {
        height: 100%;
        transform-origin: center center;
        width: 100%;
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        margin: auto;
        animation: p-progressspinner-rotate 2s linear infinite;
    }

    .p-progressspinner-circle {
        stroke-dasharray: 89, 200;
        stroke-dashoffset: 0;
        stroke: dt('progressspinner.colorOne');
        animation:
            p-progressspinner-dash 1.5s ease-in-out infinite,
            p-progressspinner-color 6s ease-in-out infinite;
        stroke-linecap: round;
    }

    @keyframes p-progressspinner-rotate {
        100% {
            transform: rotate(360deg);
        }
    }
    @keyframes p-progressspinner-dash {
        0% {
            stroke-dasharray: 1, 200;
            stroke-dashoffset: 0;
        }
        50% {
            stroke-dasharray: 89, 200;
            stroke-dashoffset: -35px;
        }
        100% {
            stroke-dasharray: 89, 200;
            stroke-dashoffset: -124px;
        }
    }
    @keyframes p-progressspinner-color {
        100%,
        0% {
            stroke: dt('progressspinner.color.one');
        }
        40% {
            stroke: dt('progressspinner.color.two');
        }
        66% {
            stroke: dt('progressspinner.color.three');
        }
        80%,
        90% {
            stroke: dt('progressspinner.color.four');
        }
    }
`,$n={root:"p-progressspinner",spin:"p-progressspinner-spin",circle:"p-progressspinner-circle"},Cn=he.extend({name:"progressspinner",style:_n,classes:$n}),Bn={name:"BaseProgressSpinner",extends:ke,props:{strokeWidth:{type:String,default:"2"},fill:{type:String,default:"none"},animationDuration:{type:String,default:"2s"}},style:Cn,provide:function(){return{$pcProgressSpinner:this,$parentInstance:this}}},Ce={name:"ProgressSpinner",extends:Bn,inheritAttrs:!1,computed:{svgStyle:function(){return{"animation-duration":this.animationDuration}}}},Sn=["fill","stroke-width"];function jn(e,n,s,d,f,o){return c(),k("div",q({class:e.cx("root"),role:"progressbar"},e.ptmi("root")),[(c(),k("svg",q({class:e.cx("spin"),viewBox:"25 25 50 50",style:o.svgStyle},e.ptm("spin")),[t("circle",q({class:e.cx("circle"),cx:"50",cy:"50",r:"20",fill:e.fill,"stroke-width":e.strokeWidth,strokeMiterlimit:"10"},e.ptm("circle")),null,16,Sn)],16))],16)}Ce.render=jn;const me="kasirKeranjang",fe=1e-4,ie=Te("kasir",()=>{const e=Ae(me,{}),n=I(Array.isArray(e.baris)?e.baris:[]),s=I(Array.isArray(e.tertunda)?e.tertunda:[]),d=I(!1);se([n,s],()=>ze(me,{baris:n.value,tertunda:s.value}),{deep:!0});const f=a=>Math.round(a.qty*a.harga)-a.diskon,o=z(()=>n.value.reduce((a,p)=>a+f(p),0)),C=z(()=>n.value.length),l=z(()=>[...new Set(n.value.map(a=>a.sparepart_id))]),i=a=>a.baris.reduce((p,r)=>p+f(r),0),g=(a,p=null)=>n.value.filter(r=>r.sparepart_id===a&&r.key!==p).reduce((r,u)=>r+u.qty*u.isi,0),P=(a,p)=>Math.round((p-g(a))*100)/100,D=a=>Number(a).toLocaleString("id-ID",{maximumFractionDigits:2});function v(a,p,r=1){const u=`${a.id}:${p.satuan_id}`,j=n.value.find(A=>A.key===u),h=(((j==null?void 0:j.qty)??0)+r)*p.isi,m=a.stok-g(a.id,u);if(h>m+fe)return{ok:!1,pesan:`Stok ${a.nama} tidak cukup (tersisa ${D(Math.max(m,0))} ${a.satuan_dasar}).`};const x={harga:p.harga_jual,isi:p.isi,satuan:p.nama,stok:a.stok,lokasi:a.lokasi??null,nama:a.nama,kode:a.kode};return j?Object.assign(j,x,{qty:Math.round((j.qty+r)*100)/100}):n.value.push({key:u,sparepart_id:a.id,satuan_id:p.satuan_id,qty:r,diskon:0,...x}),{ok:!0}}function K(a,{qty:p,diskon:r}){const u=Math.round((p??a.qty)*100)/100,j=r??a.diskon;if(u<=0)return B(a);const h=a.stok-g(a.sparepart_id,a.key);return u*a.isi>h+fe?{ok:!1,pesan:`Stok ${a.nama} tidak cukup (tersisa ${D(Math.max(h/a.isi,0))} ${a.satuan}).`}:j<0||j>Math.round(u*a.harga)?{ok:!1,pesan:"Potongan tidak boleh lebih besar dari harga."}:(a.qty=u,a.diskon=j,{ok:!0})}function B(a){return n.value=n.value.filter(p=>p.key!==a.key),{ok:!0}}const y=()=>n.value=[];function E(a){for(const p of a)for(const r of n.value){if(r.sparepart_id!==p.id)continue;r.stok=p.stok,r.lokasi=p.lokasi??null;const u=p.satuans.find(j=>j.satuan_id===r.satuan_id);u&&Object.assign(r,{harga:u.harga_jual,isi:u.isi,satuan:u.nama})}}async function M(){if(!l.value.length)return[];const a=(await J("/cari/sparepart",{ids:l.value.join(",")})).data;return E(a),a}function R(){n.value.length&&(s.value.push({id:Date.now(),baris:n.value}),n.value=[])}function T(a){const p=s.value.find(r=>r.id===a);p&&(s.value=s.value.filter(r=>r.id!==a),R(),n.value=p.baris)}const S=a=>s.value=s.value.filter(p=>p.id!==a);async function U(a,p){d.value=!0;try{const r=await Oe("/penjualan",{items:n.value.map(u=>({sparepart_id:u.sparepart_id,satuan_id:u.satuan_id,qty:u.qty,diskon:u.diskon})),metode_bayar:a,dibayar:p,total:o.value});return y(),r.data}finally{d.value=!1}}return{baris:n,tertunda:s,mengirim:d,total:o,jumlahItem:C,ids:l,subtotal:f,totalTunda:i,sisaDasar:P,tambah:v,ubah:K,hapus:B,kosongkan:y,catatBarang:E,segarkan:M,tahan:R,buka:T,buang:S,kirim:U}}),In={class:"panel grid-panel"},Ln={class:"tools"},Dn={class:"chips",role:"tablist","aria-label":"Kategori"},Kn=["aria-selected"],xn=["aria-selected","onClick"],Pn={class:"cards"},En=["disabled","aria-label","onClick"],Mn={class:"nama"},qn={key:0,class:"muted"},Vn={class:"harga"},Rn={class:"muted"},Tn=["title"],An={class:"meta"},On={key:0,class:"tanda habis-t"},zn={key:1,class:"tanda tipis-t"},Un={key:1,class:"empty"},Nn={key:2,class:"empty"},Hn={__name:"KasirGrid",emits:["pilih"],setup(e,{expose:n,emit:s}){const d=s,f=ie(),o=I(""),C=I([]),l=I(0),i=I([]),g=I(0),P=I(!1),D=I(!1),v=I(""),K=I(),B=I();let y,E=0,M;const R=h=>f.sisaDasar(h.id,h.stok),T=h=>R(h)<=0,S=h=>R(h)>0&&h.stok_min>0&&R(h)<=h.stok_min,U=h=>h.satuans.find(m=>m.satuan_id===h.satuan_dasar_id)||h.satuans[0],a=h=>h.satuans.filter(m=>m.satuan_id!==h.satuan_dasar_id);async function p(h=!1){if(D.value&&!h||(h&&(E++,g.value=0,P.value=!1,i.value=[]),P.value))return;const m=E;D.value=!0,v.value="";try{const x=await J("/cari/sparepart",{page:g.value+1,per_page:48,q:o.value||void 0,kategori_id:l.value||void 0});if(m!==E)return;i.value.push(...x.data),f.catatBarang(x.data),g.value=x.current_page,P.value=x.current_page>=x.last_page}catch{m===E&&(v.value="Barang tidak bisa dimuat.")}finally{m===E&&(D.value=!1)}}const r=()=>{clearTimeout(y),y=setTimeout(()=>p(!0),280)};se(l,()=>p(!0));async function u(){const h=o.value.trim();if(h){clearTimeout(y);try{const m=(await J("/cari/sparepart",{q:h})).data;if(m.length!==1)return p(!0);const x=m[0];d("pilih",x,x.satuans.find(A=>A.barcode===h)||null),o.value="",p(!0)}catch{}}}async function j(h){const m=h.filter(x=>i.value.some(A=>A.id===x));if(m.length)try{const x=(await J("/cari/sparepart",{ids:m.join(",")})).data;for(const A of x){const ne=i.value.findIndex(te=>te.id===A.id);ne>=0&&(i.value[ne]=A)}f.catatBarang(x)}catch{}}return ge(async()=>{p(!0);try{C.value=(await J("/cari/kategori")).data}catch{}M=new IntersectionObserver(h=>h[0].isIntersecting&&p(),{rootMargin:"200px"}),M.observe(K.value)}),Ue(()=>{clearTimeout(y),M==null||M.disconnect()}),n({fokus:()=>{var h,m;return(m=(h=B.value)==null?void 0:h.$el)==null?void 0:m.focus()},segarkan:j,muat:p}),(h,m)=>{const x=Xe,A=Ne,ne=en,te=W,Be=Qe,Se=Ce;return c(),k("section",In,[t("div",Ln,[$(ne,{class:"grow"},{default:H(()=>[$(x,{class:"pi pi-search"}),$(A,{ref_key:"cari",ref:B,modelValue:o.value,"onUpdate:modelValue":m[0]||(m[0]=w=>o.value=w),placeholder:"Cari barang, atau scan barcode lalu Enter",fluid:"",autofocus:"","aria-label":"Cari barang",onInput:r,onKeydown:He(de(u,["prevent"]),["enter"])},null,8,["modelValue","onKeydown"])]),_:1}),$(te,{icon:"pi pi-refresh",variant:"outlined",severity:"secondary","aria-label":"Muat ulang",onClick:m[1]||(m[1]=w=>p(!0))})]),t("div",Dn,[t("button",{class:Y(["chip-btn",{on:!l.value}]),role:"tab","aria-selected":!l.value,onClick:m[2]||(m[2]=w=>l.value=0)},"Semua",10,Kn),(c(!0),k(O,null,F(C.value,w=>(c(),k("button",{key:w.id,class:Y(["chip-btn",{on:l.value===w.id}]),role:"tab","aria-selected":l.value===w.id,onClick:re=>l.value=w.id},_(w.nama),11,xn))),128))]),t("div",Pn,[(c(!0),k(O,null,F(i.value,w=>{var re,ce;return c(),k("button",{key:w.id,class:"card",disabled:T(w),"aria-label":`${w.nama}, ${b(V)((re=U(w))==null?void 0:re.harga_jual)}`,onClick:oe=>d("pilih",w,null)},[t("strong",Mn,_(w.nama),1),w.merk?(c(),k("small",qn,_(w.merk),1)):L("",!0),t("span",Vn,[N(_(b(V)((ce=U(w))==null?void 0:ce.harga_jual))+" ",1),t("small",Rn,"/ "+_(w.satuan_dasar),1)]),w.lokasi?(c(),k("span",{key:1,class:"rak",title:`Diambil dari rak ${w.lokasi}`},[m[4]||(m[4]=t("i",{class:"pi pi-map-marker"},null,-1)),N(" Rak "+_(w.lokasi),1)],8,Tn)):L("",!0),t("span",An,[t("small",{class:Y(T(w)?"merah":S(w)?"oranye":"muted")},"Stok "+_(b(Q)(R(w))),3),T(w)?(c(),k("small",On,"Habis")):S(w)?(c(),k("small",zn,"Menipis")):L("",!0),(c(!0),k(O,null,F(a(w),oe=>(c(),k("small",{key:oe.satuan_id,class:"unit"},_(oe.nama),1))),128))])],8,En)}),128)),D.value&&!i.value.length?(c(),k(O,{key:0},F(12,w=>$(Be,{key:w,height:"108px","border-radius":"12px"})),64)):L("",!0),!D.value&&!i.value.length&&!v.value?(c(),k("div",Un,[$(we,{name:"sparepart",size:40}),t("span",null,_(o.value?"Barang tidak ditemukan":"Belum ada barang"),1)])):L("",!0),v.value?(c(),k("div",Nn,[t("span",null,_(v.value),1),$(te,{label:"Coba lagi",size:"small",outlined:"",onClick:m[3]||(m[3]=w=>p(!0))})])):L("",!0),t("div",{ref_key:"sentinel",ref:K,class:"sentinel","aria-hidden":"true"},[D.value&&i.value.length?(c(),Z(Se,{key:0,style:{width:"24px",height:"24px"},"stroke-width":"6"})):L("",!0)],512)])])}}},Fn=ee(Hn,[["__scopeId","data-v-86ff5258"]]),Zn={class:"panel cart"},Gn={class:"head"},Wn={class:"kanan"},Jn={key:0,class:"tunda","aria-label":"Keranjang tertunda"},Yn=["aria-label","onClick"],Qn=["onClick"],Xn={key:1,class:"empty"},et={key:2,class:"items"},nt=["aria-label","onClick"],tt={class:"muted"},at={key:0,class:"rak"},st={class:"qty"},it=["onClick"],rt={class:"num"},ot=["onClick"],lt={class:"sub num"},ut=["onClick"],dt={class:"foot"},ct={class:"row total"},pt={class:"num"},mt={__name:"KasirKeranjang",emits:["bayar","edit","kosongkan","buka","buang","pesan"],setup(e,{emit:n}){const s=n,d=ie(),f=I(),o=z(()=>[{label:"Keranjang baru (tahan yang ini)",icon:"pi pi-pause",disabled:!d.jumlahItem,command:()=>d.tahan()},{separator:!0},{label:"Kosongkan keranjang",icon:"pi pi-trash",disabled:!d.jumlahItem,command:()=>s("kosongkan")}]);function C(l,i){const g=d.ubah(l,{qty:l.qty+i});g.ok||s("pesan",g.pesan)}return(l,i)=>{const g=nn,P=W,D=Fe;return c(),k("section",Zn,[t("header",Gn,[i[2]||(i[2]=t("i",{class:"pi pi-shopping-cart ikon"},null,-1)),i[3]||(i[3]=t("strong",null,"Keranjang",-1)),b(d).jumlahItem?(c(),Z(g,{key:0,value:`${b(d).jumlahItem} jenis`,severity:"secondary"},null,8,["value"])):L("",!0),t("span",Wn,[$(P,{icon:"pi pi-ellipsis-v",text:"",rounded:"",severity:"secondary","aria-label":"Aksi keranjang","aria-haspopup":"true",onClick:i[0]||(i[0]=v=>f.value.toggle(v))}),$(D,{ref_key:"menu",ref:f,model:o.value,popup:""},null,8,["model"])])]),b(d).tertunda.length?(c(),k("nav",Jn,[i[5]||(i[5]=t("span",{class:"muted"},"Tertunda:",-1)),(c(!0),k(O,null,F(b(d).tertunda,(v,K)=>(c(),k("span",{key:v.id,class:"chip"},[t("button",{class:"pill","aria-label":`Buka keranjang tertunda ${K+1}`,onClick:B=>s("buka",v.id)},[N(_(K+1)+" ",1),t("small",null,_(b(V)(b(d).totalTunda(v))),1)],8,Yn),t("button",{class:"buang","aria-label":"Buang keranjang tertunda",onClick:B=>s("buang",v.id)},[...i[4]||(i[4]=[t("i",{class:"pi pi-times"},null,-1)])],8,Qn)]))),128))])):L("",!0),b(d).jumlahItem?(c(),k("ul",et,[(c(!0),k(O,null,F(b(d).baris,v=>(c(),k("li",{key:v.key,class:"item"},[t("button",{class:"nm","aria-label":`Ubah ${v.nama}`,onClick:K=>s("edit",v)},[t("strong",null,_(v.nama),1),t("small",tt,[N(_(b(V)(v.harga))+" / "+_(v.satuan),1),v.diskon?(c(),k(O,{key:0},[N(" · potongan "+_(b(V)(v.diskon)),1)],64)):L("",!0)]),v.lokasi?(c(),k("small",at,[i[7]||(i[7]=t("i",{class:"pi pi-map-marker"},null,-1)),N(" Rak "+_(v.lokasi),1)])):L("",!0)],8,nt),t("span",st,[t("button",{class:"step","aria-label":"Kurangi",onClick:K=>C(v,-1)},[...i[8]||(i[8]=[t("i",{class:"pi pi-minus"},null,-1)])],8,it),t("b",rt,_(b(Q)(v.qty)),1),t("button",{class:"step","aria-label":"Tambah",onClick:K=>C(v,1)},[...i[9]||(i[9]=[t("i",{class:"pi pi-plus"},null,-1)])],8,ot)]),t("strong",lt,_(b(V)(b(d).subtotal(v))),1),t("button",{class:"hapus","aria-label":"Hapus",onClick:K=>b(d).hapus(v)},[...i[10]||(i[10]=[t("i",{class:"pi pi-times"},null,-1)])],8,ut)]))),128))])):(c(),k("div",Xn,[...i[6]||(i[6]=[t("i",{class:"pi pi-shopping-cart"},null,-1),t("strong",null,"Keranjang masih kosong",-1),t("small",null,"Ketuk barang di sebelah, atau scan barcode",-1)])])),t("footer",dt,[t("div",ct,[i[11]||(i[11]=t("span",null,"Total",-1)),t("strong",pt,_(b(V)(b(d).total)),1)]),$(P,{label:"Bayar",icon:"pi pi-wallet",size:"large",fluid:"",disabled:!b(d).jumlahItem,onClick:i[1]||(i[1]=v=>s("bayar"))},null,8,["disabled"])])])}}},ve=ee(mt,[["__scopeId","data-v-1c34b2e0"]]),ft={key:0,class:"lokasi"},vt={class:"field"},ht={class:"unit-grid",role:"radiogroup","aria-label":"Satuan"},kt=["aria-checked","disabled","onClick"],bt={class:"num"},yt={class:"muted"},gt={class:"field"},wt={class:"qty"},_t={key:0,class:"err"},$t={class:"total"},Ct={class:"num"},Bt={class:"actions"},St={__name:"SatuanDialog",props:ae({sparepart:Object},{visible:{},visibleModifiers:{}}),emits:ae(["tambah"],["update:visible"]),setup(e,{emit:n}){const s=e,d=be(e,"visible"),f=n,o=ie(),C=I(null),l=I(1);se(()=>s.sparepart,B=>{B&&(C.value=B.satuan_dasar_id,l.value=1)});const i=z(()=>{var B;return(B=s.sparepart)==null?void 0:B.satuans.find(y=>y.satuan_id===C.value)}),g=z(()=>i.value?Math.round((l.value||0)*i.value.harga_jual):0),P=z(()=>o.sisaDasar(s.sparepart.id,s.sparepart.stok)),D=B=>Math.floor(Math.round(P.value/B.isi*100)/100),v=z(()=>i.value&&(l.value||0)*i.value.isi<=P.value+1e-4);function K(){f("tambah",s.sparepart,i.value,l.value),d.value=!1}return(B,y)=>{var T;const E=W,M=_e,R=ye;return c(),Z(R,{visible:d.value,"onUpdate:visible":y[4]||(y[4]=S=>d.value=S),modal:"",header:(T=e.sparepart)==null?void 0:T.nama,style:{width:"28rem"},breakpoints:{"600px":"94vw"}},{default:H(()=>[e.sparepart?(c(),k("form",{key:0,class:"form",onSubmit:de(K,["prevent"])},[e.sparepart.lokasi?(c(),k("p",ft,[y[5]||(y[5]=t("i",{class:"pi pi-map-marker"},null,-1)),y[6]||(y[6]=N(" Ambil di ",-1)),t("strong",null,"Rak "+_(e.sparepart.lokasi),1)])):L("",!0),t("div",vt,[y[7]||(y[7]=t("label",null,"Pilih satuan",-1)),t("div",ht,[(c(!0),k(O,null,F(e.sparepart.satuans,S=>(c(),k("button",{key:S.satuan_id,type:"button",role:"radio",class:Y(["unit",{on:S.satuan_id===C.value}]),"aria-checked":S.satuan_id===C.value,disabled:D(S)<1,onClick:U=>C.value=S.satuan_id},[t("strong",null,_(S.nama),1),t("span",bt,_(b(V)(S.harga_jual)),1),t("small",yt,_(S.isi===1?`Stok ${b(Q)(D(S))}`:`isi ${b(Q)(S.isi)} ${e.sparepart.satuan_dasar} · stok ${b(Q)(D(S))}`),1)],10,kt))),128))])]),t("div",gt,[y[8]||(y[8]=t("label",{for:"qty-satuan"},"Jumlah",-1)),t("div",wt,[$(E,{type:"button",icon:"pi pi-minus",severity:"secondary",outlined:"",rounded:"",disabled:l.value<=1,"aria-label":"Kurangi",onClick:y[0]||(y[0]=S=>l.value=Math.max(1,(l.value||1)-1))},null,8,["disabled"]),$(M,{modelValue:l.value,"onUpdate:modelValue":y[1]||(y[1]=S=>l.value=S),"input-id":"qty-satuan",min:0,"max-fraction-digits":2,locale:"id-ID",class:"qty-input",autofocus:""},null,8,["modelValue"]),$(E,{type:"button",icon:"pi pi-plus",severity:"secondary",outlined:"",rounded:"","aria-label":"Tambah",onClick:y[2]||(y[2]=S=>l.value=(l.value||0)+1)})]),v.value?L("",!0):(c(),k("small",_t,"Stok tidak cukup untuk jumlah ini."))]),t("div",$t,[y[9]||(y[9]=t("span",null,"Subtotal",-1)),t("strong",Ct,_(b(V)(g.value)),1)]),t("div",Bt,[$(E,{type:"button",label:"Batal",severity:"secondary",outlined:"",onClick:y[3]||(y[3]=S=>d.value=!1)}),$(E,{type:"submit",label:"Masukkan ke Keranjang",icon:"pi pi-plus",disabled:!v.value||!l.value},null,8,["disabled"])])],32)):L("",!0)]),_:1},8,["visible","header"])}}},jt=ee(St,[["__scopeId","data-v-931e06dc"]]),It={class:"kasir"},Lt={class:"body"},Dt={class:"num"},Kt={__name:"KasirView",setup(e){const n=Je(),s=Ze(),d=Ge(),f=ie(),o=We(),C=I(),l=I({satuan:!1,edit:!1,bayar:!1,cart:!1}),i=I(null),g=I(null);let P=[];const D=z(()=>f.jumlahItem?`${f.jumlahItem} jenis barang di keranjang`:"Ketuk barang untuk mulai menjual");ge(async()=>{var r;(r=C.value)==null||r.fokus(),f.jumlahItem&&f.segarkan().catch(()=>{})});const v=r=>s.add({severity:"warn",summary:"Tidak bisa ditambahkan",detail:r,life:4500});function K(r,u,j=1){var m;const h=f.tambah(r,u,j);if(!h.ok)return v(h.pesan);(m=C.value)==null||m.fokus()}function B(r,u){return u?K(r,u):r.satuans.length>1?(i.value=r,l.value.satuan=!0):K(r,r.satuans[0])}function y(r){g.value=r,l.value.edit=!0}function E(r,u){const j=f.ubah(r,u);j.ok||v(j.pesan)}function M(){P=[...f.ids],l.value.bayar=!0,l.value.cart=!1}const R=()=>{var r;return(r=C.value)==null?void 0:r.segarkan(P)};async function T(){var r;try{const u=await f.segarkan();(r=C.value)==null||r.segarkan(u.map(j=>j.id))}catch{}}const S=(r,u)=>f.kirim(r,u);function U(){d.require({header:"Kosongkan keranjang?",message:`${f.jumlahItem} jenis barang akan dibuang dari keranjang.`,icon:"pi pi-trash",acceptProps:{label:"Kosongkan",severity:"danger"},rejectProps:{label:"Batal",severity:"secondary",outlined:!0},accept:()=>f.kosongkan()})}function a(r){d.require({header:"Buang keranjang tertunda?",message:"Isinya akan hilang dan tidak bisa dikembalikan.",icon:"pi pi-trash",acceptProps:{label:"Buang",severity:"danger"},rejectProps:{label:"Batal",severity:"secondary",outlined:!0},accept:()=>f.buang(r)})}function p(r){f.buka(r),l.value.cart=!1}return(r,u)=>{const j=W,h=$e;return c(),k("div",It,[$(tn,{title:"Kasir Sparepart",subtitle:D.value},{default:H(()=>[$(j,{label:"Papan Servis",icon:"pi pi-th-large",severity:"secondary",outlined:"",onClick:u[0]||(u[0]=m=>b(n).push("/servis"))})]),_:1},8,["subtitle"]),t("div",Lt,[$(Fn,{ref_key:"grid",ref:C,onPilih:B},null,512),b(o)?L("",!0):(c(),Z(ve,{key:0,onBayar:M,onEdit:y,onKosongkan:U,onBuka:p,onBuang:a,onPesan:v}))]),b(o)?(c(),k(O,{key:0},[t("button",{class:"cart-bar",onClick:u[1]||(u[1]=m=>l.value.cart=!0)},[t("span",null,[$(we,{name:"jual",size:20}),N(" "+_(b(f).jumlahItem)+" jenis barang",1)]),t("strong",Dt,_(b(V)(b(f).total)),1)]),$(h,{visible:l.value.cart,"onUpdate:visible":u[2]||(u[2]=m=>l.value.cart=m),position:"bottom",header:"Keranjang",class:"cart-drawer"},{default:H(()=>[$(ve,{onBayar:M,onEdit:y,onKosongkan:U,onBuka:p,onBuang:a,onPesan:v})]),_:1},8,["visible"])],64)):L("",!0),$(jt,{visible:l.value.satuan,"onUpdate:visible":u[3]||(u[3]=m=>l.value.satuan=m),sparepart:i.value,onTambah:K},null,8,["visible","sparepart"]),$(wn,{visible:l.value.edit,"onUpdate:visible":u[4]||(u[4]=m=>l.value.edit=m),baris:g.value,onSimpan:E,onHapus:b(f).hapus},null,8,["visible","baris","onHapus"]),$(Ye,{visible:l.value.bayar,"onUpdate:visible":u[5]||(u[5]=m=>l.value.bayar=m),total:b(f).total,kirim:S,onPaid:R,onGagal:T},null,8,["visible","total"])])}}},Tt=ee(Kt,[["__scopeId","data-v-7283ddca"]]);export{Tt as default};
