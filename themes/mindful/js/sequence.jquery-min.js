(function(e){
    function n(n,s,o,f){
        function d(){
            l.afterLoaded();
            l.settings.hideFramesUntilPreloaded&&l.settings.preloader!==undefined&&l.settings.preloader!==!1&&l.frames.show();
            if(l.settings.preloader!==undefined&&l.settings.preloader!==!1)if(l.settings.hidePreloaderUsingCSS&&l.transitionsSupported){
                l.prependPreloadingCompleteTo=l.settings.prependPreloadingComplete===!0?l.settings.preloader:e(l.settings.prependPreloadingComplete);
                l.prependPreloadingCompleteTo.addClass("preloading-complete");
                setTimeout(S,l.settings.hidePreloaderDelay)
            }else l.settings.preloader.fadeOut(l.settings.hidePreloaderDelay,function(){
                clearInterval(l.defaultPreloader);
                S()
            });else S()
        }
        function g(t,n){
            var r=[];
            if(!n)for(var i=t;i>0;i--)l.frames.eq(l.settings.preloadTheseFrames[i-1]-1).find("img").each(function(){
                r.push(e(this)[0])
            });else for(var s=t;s>0;s--)r.push(e("body").find('img[src="'+l.settings.preloadTheseImages[s-1]+'"]'));
            return r
        }
        function y(t,n){
            function c(){
                var t=e(f),r=e(l);
                s&&(l.length?s.reject(u,t,r):s.resolve(u));
                e.isFunction(n)&&n.call(i,u,t,r)
            }
            function h(t,n){
                if(t.src===r||e.inArray(t,a)!==-1)return;
                a.push(t);
                n?l.push(t):f.push(t);
                e.data(t,"imagesLoaded",{
                    isBroken:n,
                    src:t.src
                });
                o&&s.notifyWith(e(t),[n,u,e(f),e(l)]);
                if(u.length===a.length){
                    setTimeout(c);
                    u.unbind(".imagesLoaded")
                }
            }
            var r="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==",i=t,s=e.isFunction(e.Deferred)?e.Deferred():0,o=e.isFunction(s.notify),u=i.find("img").add(i.filter("img")),a=[],f=[],l=[];
            e.isPlainObject(n)&&e.each(n,function(e,t){
                e==="callback"?n=t:s&&s[e](t)
            });
            u.length?u.bind("load.imagesLoaded error.imagesLoaded",function(e){
                h(e.target,e.type==="error")
            }).each(function(t,n){
                var i=n.src,s=e.data(n,"imagesLoaded");
                if(s&&s.src===i){
                    h(n,s.isBroken);
                    return
                }
                if(n.complete&&n.naturalWidth!==undefined){
                    h(n,n.naturalWidth===0||n.naturalHeight===0);
                    return
                }
                if(n.readyState||n.complete){
                    n.src=r;
                    n.src=i
                }
            }):c()
        }
        function S(){
            function t(e,t){
                var i,s;
                for(s in t){
                    s==="left"||s==="right"?i=n[s]:i=s;
                    e===parseFloat(i)&&r(l,t[s])
                }
            }
            function s(){
                l.canvas.on("touchmove.sequence",o);
                c=null;
                p=!1
            }
            function o(e){
                l.settings.swipePreventsDefault&&e.preventDefault();
                if(p){
                    var t=e.originalEvent.touches[0].pageX,n=e.originalEvent.touches[0].pageY,i=c-t,o=h-n;
                    if(Math.abs(i)>=l.settings.swipeThreshold){
                        s();
                        i>0?r(l,l.settings.swipeEvents.left):r(l,l.settings.swipeEvents.right)
                    }else if(Math.abs(o)>=l.settings.swipeThreshold){
                        s();
                        o>0?r(l,l.settings.swipeEvents.down):r(l,l.settings.swipeEvents.up)
                    }
                }
            }
            function f(e){
                if(e.originalEvent.touches.length===1){
                    c=e.originalEvent.touches[0].pageX;
                    h=e.originalEvent.touches[0].pageY;
                    p=!0;
                    l.canvas.on("touchmove.sequence",o)
                }
            }
            e(l.settings.preloader).remove();
            l.nextButton=a(l,l.settings.nextButton,".sequence-next");
            l.prevButton=a(l,l.settings.prevButton,".sequence-prev");
            l.pauseButton=a(l,l.settings.pauseButton,".sequence-pause");
            l.pagination=a(l,l.settings.pagination,".sequence-pagination");
            l.nextButton!==undefined&&l.nextButton!==!1&&l.settings.showNextButtonOnInit===!0&&l.nextButton.show();
            l.prevButton!==undefined&&l.prevButton!==!1&&l.settings.showPrevButtonOnInit===!0&&l.prevButton.show();
            l.pauseButton!==undefined&&l.pauseButton!==!1&&l.settings.showPauseButtonOnInit===!0&&l.pauseButton.show();
            if(l.settings.pauseIcon!==!1){
                l.pauseIcon=a(l,l.settings.pauseIcon,".sequence-pause-icon");
                l.pauseIcon!==undefined&&l.pauseIcon.hide()
            }else l.pauseIcon=undefined;
            if(l.pagination!==undefined&&l.pagination!==!1){
                l.paginationLinks=l.pagination.children();
                l.paginationLinks.on("click.sequence",function(){
                    var t=e(this).index()+1;
                    l.goTo(t)
                });
                l.settings.showPaginationOnInit===!0&&l.pagination.show()
            }
            l.nextFrameID=l.settings.startingFrameID;
            if(l.settings.hashTags===!0){
                l.frames.each(function(){
                    l.frameHashID.push(e(this).prop(l.getHashTagFrom))
                });
                l.currentHashTag=location.hash.replace("#","");
                if(l.currentHashTag===undefined||l.currentHashTag==="")l.nextFrameID=l.settings.startingFrameID;
                else{
                    l.frameHashIndex=e.inArray(l.currentHashTag,l.frameHashID);
                    l.frameHashIndex!==-1?l.nextFrameID=l.frameHashIndex+1:l.nextFrameID=l.settings.startingFrameID
                }
            }
            l.nextFrame=l.frames.eq(l.nextFrameID-1);
            l.nextFrameChildren=l.nextFrame.children();
            l.pagination!==undefined&&e(l.paginationLinks[l.settings.startingFrameID-1]).addClass("current");
            if(l.transitionsSupported)if(!l.settings.animateStartingFrameIn){
                l.currentFrameID=l.nextFrameID;
                l.settings.moveActiveFrameToTop&&l.nextFrame.css("z-index",l.numberOfFrames);
                i(l.prefix,l.nextFrameChildren,"0s");
                l.nextFrame.addClass("animate-in");
                if(l.settings.hashTags&&l.settings.hashChangesOnFirstFrame){
                    l.currentHashTag=l.nextFrame.prop(l.getHashTagFrom);
                    document.location.hash="#"+l.currentHashTag
                }
                setTimeout(function(){
                    i(l.prefix,l.nextFrameChildren,"")
                },100);
                u(l,!0,l.settings.autoPlayDelay)
            }else if(l.settings.reverseAnimationsWhenNavigatingBackwards&&l.settings.autoPlayDirection-1&&l.settings.animateStartingFrameIn){
                i(l.prefix,l.nextFrameChildren,"0s");
                l.nextFrame.addClass("animate-out");
                l.goTo(l.nextFrameID,-1,!0)
            }else l.goTo(l.nextFrameID,1,!0);
            else{
                l.container.addClass("sequence-fallback");
                l.currentFrameID=l.nextFrameID;
                if(l.settings.hashTags&&l.settings.hashChangesOnFirstFrame){
                    l.currentHashTag=l.nextFrame.prop(l.getHashTagFrom);
                    document.location.hash="#"+l.currentHashTag
                }
                l.frames.addClass("animate-in");
                l.frames.not(":eq("+(l.nextFrameID-1)+")").css({
                    display:"none",
                    opacity:0
                });
                u(l,!0,l.settings.autoPlayDelay)
            }
            l.nextButton!==undefined&&l.nextButton.bind("click.sequence",function(){
                l.next()
            });
            l.prevButton!==undefined&&l.prevButton.bind("click.sequence",function(){
                l.prev()
            });
            l.pauseButton!==undefined&&l.pauseButton.bind("click.sequence",function(){
                l.pause(!0)
            });
            if(l.settings.keyNavigation){
                var n={
                    left:37,
                    right:39
                };
                e(document).bind("keydown.sequence",function(e){
                    var n=String.fromCharCode(e.keyCode);
                    if(n>0&&n<=l.numberOfFrames&&l.settings.numericKeysGoToFrames){
                        l.nextFrameID=n;
                        l.goTo(l.nextFrameID)
                    }
                    t(e.keyCode,l.settings.keyEvents);
                    t(e.keyCode,l.settings.customKeyEvents)
                })
            }
            l.settings.pauseOnHover&&l.settings.autoPlay&&!l.hasTouch&&l.canvas.on({
                "mouseenter.sequence":function(){
                    l.isBeingHoveredOver=!0;
                    l.isHardPaused||l.pause()
                },
                "mouseleave.sequence":function(){
                    l.isBeingHoveredOver=!1;
                    l.isHardPaused||l.unpause()
                }
            });
            l.settings.hashTags&&e(window).bind("hashchange.sequence",function(){
                var t=location.hash.replace("#","");
                if(l.currentHashTag!==t){
                    l.currentHashTag=t;
                    l.frameHashIndex=e.inArray(l.currentHashTag,l.frameHashID);
                    if(l.frameHashIndex!==-1){
                        l.nextFrameID=l.frameHashIndex+1;
                        l.goTo(l.nextFrameID)
                    }
                }
            });
            if(l.settings.swipeNavigation&&l.hasTouch){
                var c,h,p=!1;
                l.canvas.on("touchstart.sequence",f)
            }
        }
        var l=this;
        l.container=e(n);
        l.canvas=l.container.children(".sequence-canvas");
        l.frames=l.canvas.children("li");
        try{
            Modernizr.prefixed;
            if(Modernizr.prefixed===undefined)throw"undefined"
        }catch(c){
            f.modernizr()
        }
        var h={
            WebkitTransition:"-webkit-",
            MozTransition:"-moz-",
            OTransition:"-o-",
            msTransition:"-ms-",
            transition:""
        },p={
            WebkitTransition:"webkitTransitionEnd.sequence webkitAnimationEnd.sequence",
            MozTransition:"transitionend.sequence animationend.sequence",
            OTransition:"otransitionend.sequence oanimationend.sequence",
            msTransition:"MSTransitionEnd.sequence MSAnimationEnd.sequence",
            transition:"transitionend.sequence animationend.sequence"
        };
        l.prefix=h[Modernizr.prefixed("transition")],l.transitionProperties={},l.transitionEnd=p[Modernizr.prefixed("transition")],l.numberOfFrames=l.frames.length,l.transitionsSupported=l.prefix!==undefined?!0:!1,l.hasTouch="ontouchstart"in window?!0:!1,l.isPaused=!1,l.isBeingHoveredOver=!1,l.container.removeClass("sequence-destroyed");
        l.paused=function(){},l.unpaused=function(){},l.beforeNextFrameAnimatesIn=function(){},l.afterNextFrameAnimatesIn=function(){},l.beforeCurrentFrameAnimatesOut=function(){},l.afterCurrentFrameAnimatesOut=function(){},l.afterLoaded=function(){};
        l.destroyed=function(){};
        l.settings=e.extend({},o,s);
        l.settings.preloader=a(l,l.settings.preloader,".sequence-preloader");
        l.isStartingFrame=l.settings.animateStartingFrameIn?!0:!1;
        l.settings.unpauseDelay=l.settings.unpauseDelay===null?l.settings.autoPlayDelay:l.settings.unpauseDelay;
        l.getHashTagFrom=l.settings.hashDataAttribute?"data-sequence-hashtag":"id";
        l.frameHashID=[];
        l.direction=l.settings.autoPlayDirection;
        l.settings.hideFramesUntilPreloaded&&l.settings.preloader!==undefined&&l.settings.preloader!==!1&&l.frames.hide();
        l.prefix==="-o-"&&(l.transitionsSupported=f.operaTest());
        l.frames.removeClass("animate-in");
        var v=l.settings.preloadTheseFrames.length,m=l.settings.preloadTheseImages.length;
        if(l.settings.preloader===undefined||l.settings.preloader===!1||v===0&&m===0)if(t===!0){
            d();
            e(this).unbind("load.sequence")
        }else e(window).bind("load.sequence",function(){
            d();
            e(this).unbind("load.sequence")
        });
        else{
            var b=g(v),w=g(m,!0),E=e(b.concat(w));
            y(E,d)
        }
    }
    var t=!1;
    e(window).bind("load",function(){
        t=!0
    });
    n.prototype={
        startAutoPlay:function(e){
            var t=this;
            e=e===undefined?t.settings.autoPlayDelay:e;
            t.unpause();
            u(t);
            t.autoPlayTimer=setTimeout(function(){
                t.settings.autoPlayDirection===1?t.next():t.prev()
            },e)
        },
        stopAutoPlay:function(){
            var e=this;
            e.pause(!0);
            clearTimeout(e.autoPlayTimer)
        },
        pause:function(e){
            var t=this;
            if(!t.isSoftPaused){
                if(t.pauseButton!==undefined){
                    t.pauseButton.addClass("paused");
                    t.pauseIcon!==undefined&&t.pauseIcon.show()
                }
                t.paused();
                t.isSoftPaused=!0;
                t.isHardPaused=e?!0:!1;
                t.isPaused=!0;
                u(t)
            }else t.unpause()
        },
        unpause:function(e){
            var t=this;
            if(t.pauseButton!==undefined){
                t.pauseButton.removeClass("paused");
                t.pauseIcon!==undefined&&t.pauseIcon.hide()
            }
            t.isSoftPaused=!1;
            t.isHardPaused=!1;
            t.isPaused=!1;
            if(!t.active){
                e!==!1&&t.unpaused();
                u(t,!0,t.settings.unpauseDelay)
            }else t.delayUnpause=!0
        },
        next:function(){
            var e=this;
            e.nextFrameID=e.currentFrameID!==e.numberOfFrames?e.currentFrameID+1:1;
            e.goTo(e.nextFrameID,1)
        },
        prev:function(){
            var e=this;
            e.nextFrameID=e.currentFrameID===1?e.numberOfFrames:e.currentFrameID-1;
            e.goTo(e.nextFrameID,-1)
        },
        goTo:function(t,n,r){
            var o=this;
            t=parseFloat(t);
            var a=r===!0?0:o.settings.transitionThreshold;
            if(t===o.currentFrameID||o.settings.navigationSkip&&o.navigationSkipThresholdActive||!o.settings.navigationSkip&&o.active||!o.transitionsSupported&&o.active||!o.settings.cycle&&n===1&&o.currentFrameID===o.numberOfFrames||!o.settings.cycle&&n===-1&&o.currentFrameID===1||o.settings.preventReverseSkipping&&o.direction!==n&&o.active)return!1;
            if(o.settings.navigationSkip&&o.active){
                o.navigationSkipThresholdActive=!0;
                o.settings.fadeFrameWhenSkipped&&o.nextFrame.stop().animate({
                    opacity:0
                },o.settings.fadeFrameTime);
                clearTimeout(o.transitionThresholdTimer);
                setTimeout(function(){
                    o.navigationSkipThresholdActive=!1
                },o.settings.navigationSkipThreshold)
            }
            if(!o.active||o.settings.navigationSkip){
                o.active=!0;
                u(o);
                n===undefined?o.direction=t>o.currentFrameID?1:-1:o.direction=n;
                o.currentFrame=o.canvas.children(".animate-in");
                o.nextFrame=o.frames.eq(t-1);
                o.currentFrameChildren=o.currentFrame.children();
                o.nextFrameChildren=o.nextFrame.children();
                if(o.pagination!==undefined){
                    o.paginationLinks.removeClass("current");
                    e(o.paginationLinks[t-1]).addClass("current")
                }
                if(o.transitionsSupported){
                    if(o.currentFrame.length!==undefined){
                        o.beforeCurrentFrameAnimatesOut();
                        o.settings.moveActiveFrameToTop&&o.currentFrame.css("z-index",1);
                        i(o.prefix,o.nextFrameChildren,"0s");
                        if(!o.settings.reverseAnimationsWhenNavigatingBackwards||o.direction===1){
                            o.nextFrame.removeClass("animate-out");
                            i(o.prefix,o.currentFrameChildren,"")
                        }else if(o.settings.reverseAnimationsWhenNavigatingBackwards&&o.direction===-1){
                            o.nextFrame.addClass("animate-out");
                            s(o)
                        }
                    }else o.isStartingFrame=!1;
                    o.active=!0;
                    o.currentFrame.unbind(o.transitionEnd);
                    o.nextFrame.unbind(o.transitionEnd);
                    o.settings.fadeFrameWhenSkipped&&o.nextFrame.css("opacity",1);
                    o.beforeNextFrameAnimatesIn();
                    o.settings.moveActiveFrameToTop&&o.nextFrame.css("z-index",o.numberOfFrames);
                    if(!o.settings.reverseAnimationsWhenNavigatingBackwards||o.direction===1){
                        setTimeout(function(){
                            i(o.prefix,o.nextFrameChildren,"");
                            f(o,o.nextFrame,o.nextFrameChildren,"in");
                            (o.afterCurrentFrameAnimatesOut!=="function () {}"||o.settings.transitionThreshold===!0&&r!==!0)&&f(o,o.currentFrame,o.currentFrameChildren,"out",!0,1)
                        },50);
                        setTimeout(function(){
                            o.currentFrame.toggleClass("animate-out animate-in");
                            if(o.settings.transitionThreshold!==!0||r===!0)o.transitionThresholdTimer=setTimeout(function(){
                                o.nextFrame.addClass("animate-in")
                            },a)
                        },50)
                    }else if(o.settings.reverseAnimationsWhenNavigatingBackwards&&o.direction===-1){
                        setTimeout(function(){
                            i(o.prefix,o.currentFrameChildren,"");
                            i(o.prefix,o.nextFrameChildren,"");
                            s(o);
                            f(o,o.nextFrame,o.nextFrameChildren,"in");
                            (o.afterCurrentFrameAnimatesOut!=="function () {}"||o.settings.transitionThreshold===!0&&r!==!0)&&f(o,o.currentFrame,o.currentFrameChildren,"out",!0,-1)
                        },50);
                        setTimeout(function(){
                            o.currentFrame.removeClass("animate-in");
                            if(o.settings.transitionThreshold!==!0||r===!0)o.transitionThresholdTimer=setTimeout(function(){
                                o.nextFrame.toggleClass("animate-out animate-in")
                            },a)
                        },50)
                    }
                }else{
                    function c(){
                        l(o);
                        o.active=!1;
                        u(o,!0,o.settings.autoPlayDelay)
                    }
                    switch(o.settings.fallback.theme){
                        case"fade":
                            o.frames.css({
                                position:"relative"
                            });
                            o.beforeCurrentFrameAnimatesOut();
                            o.currentFrame=o.frames.eq(o.currentFrameID-1);
                            o.currentFrame.animate({
                                opacity:0
                            },o.settings.fallback.speed,function(){
                                o.currentFrame.css({
                                    display:"none",
                                    "z-index":"1"
                                });
                                o.afterCurrentFrameAnimatesOut();
                                o.beforeNextFrameAnimatesIn();
                                o.nextFrame.css({
                                    display:"block",
                                    "z-index":o.numberOfFrames
                                }).animate({
                                    opacity:1
                                },500,function(){
                                    o.afterNextFrameAnimatesIn()
                                });
                                c()
                            });
                            o.frames.css({
                                position:"relative"
                            });
                            break;
                        case"slide":default:
                            var h={},p={},d={};
                            if(o.direction===1){
                                h.left="-100%";
                                p.left="100%"
                            }else{
                                h.left="100%";
                                p.left="-100%"
                            }
                            d.left="0";
                            d.opacity=1;
                            o.currentFrame=o.frames.eq(o.currentFrameID-1);
                            o.beforeCurrentFrameAnimatesOut();
                            o.currentFrame.animate(h,o.settings.fallback.speed,function(){
                                o.afterCurrentFrameAnimatesOut()
                            });
                            o.beforeNextFrameAnimatesIn();
                            o.nextFrame.show().css(p);
                            o.nextFrame.animate(d,o.settings.fallback.speed,function(){
                                c();
                                o.afterNextFrameAnimatesIn()
                            })
                    }
                }
                o.currentFrameID=t
            }
        },
        destroy:function(t){
            var n=this;
            n.container.addClass("sequence-destroyed");
            n.nextButton!==undefined&&n.nextButton.unbind("click.sequence");
            n.prevButton!==undefined&&n.prevButton.unbind("click.sequence");
            n.pauseButton!==undefined&&n.pauseButton.unbind("click.sequence");
            n.pagination!==undefined&&n.paginationLinks.unbind("click.sequence");
            e(document).unbind("keydown.sequence");
            n.canvas.unbind("mouseenter.sequence, mouseleave.sequence, touchstart.sequence, touchmove.sequence");
            e(window).unbind("hashchange.sequence");
            n.stopAutoPlay();
            clearTimeout(n.transitionThresholdTimer);
            n.canvas.children("li").remove();
            n.canvas.prepend(n.frames);
            n.frames.removeClass("animate-in animate-out").removeAttr("style");
            n.frames.eq(n.currentFrameID-1).addClass("animate-in");
            n.nextButton!==undefined&&n.nextButton!==!1&&n.nextButton.hide();
            n.prevButton!==undefined&&n.prevButton!==!1&&n.prevButton.hide();
            n.pauseButton!==undefined&&n.pauseButton!==!1&&n.pauseButton.hide();
            n.pauseIcon!==undefined&&n.pauseIcon!==!1&&n.pauseIcon.hide();
            n.pagination!==undefined&&n.pagination!==!1&&n.pagination.hide();
            t!==undefined&&t();
            n.destroyed();
            n.container.removeData()
        }
    };
    var r=function(e,t){
        switch(t){
            case"next":
                e.next();
                break;
            case"prev":
                e.prev();
                break;
            case"pause":
                e.pause(!0)
        }
    },i=function(e,t,n){
        t.css(o(e,{
            "transition-duration":n,
            "transition-delay":n,
            "transition-timing-function":""
        }))
    },s=function(t){
        var n=[],r=[];
        t.currentFrameChildren.each(function(){
            n.push(parseFloat(e(this).css(t.prefix+"transition-duration").replace("s",""))+parseFloat(e(this).css(t.prefix+"transition-delay").replace("s","")))
        });
        t.nextFrameChildren.each(function(){
            r.push(parseFloat(e(this).css(t.prefix+"transition-duration").replace("s",""))+parseFloat(e(this).css(t.prefix+"transition-delay").replace("s","")))
        });
        var i=Math.max.apply(Math,n),s=Math.max.apply(Math,r),u=i-s,a=0,f=0;
        u<0&&!t.settings.preventDelayWhenReversingAnimations?a=Math.abs(u):u>0&&(f=Math.abs(u));
        var l=function(n,r,i,s){
            r.each(function(){
                var r=parseFloat(e(this).css(t.prefix+"transition-duration").replace("s","")),u=parseFloat(e(this).css(t.prefix+"transition-delay").replace("s","")),a=e(this).css(t.prefix+"transition-timing-function");
                if(a.indexOf("cubic-bezier")>=0){
                    var f=a.replace("cubic-bezier(","").replace(")","").split(",");
                    e.each(f,function(e,t){
                        f[e]=parseFloat(t)
                    });
                    var l=[1-f[2],1-f[3],1-f[0],1-f[1]];
                    a="cubic-bezier("+l+")"
                }else a="linear";
                var c=r+u;
                n["transition-duration"]=r+"s";
                n["transition-delay"]=i-c+s+"s";
                n["transition-timing-function"]=a;
                e(this).css(o(t.prefix,n))
            })
        };
        l(t.transitionProperties,t.currentFrameChildren,i,a);
        l(t.transitionProperties,t.nextFrameChildren,s,f)
    },o=function(e,t){
        var n={};
        for(var r in t)n[e+r]=t[r];return n
    },u=function(e,t,n){
        if(t===!0){
            if(e.settings.autoPlay&&!e.isSoftPaused){
                clearTimeout(e.autoPlayTimer);
                e.autoPlayTimer=setTimeout(function(){
                    e.settings.autoPlayDirection===1?e.next():e.prev()
                },n)
            }
        }else clearTimeout(e.autoPlayTimer)
    },a=function(t,n,r){
        switch(n){
            case!1:
                return undefined;
            case!0:
                r===".sequence-preloader"&&c.defaultPreloader(t.container,t.transitionsSupported,t.prefix);
                return e(r);
            default:
                return e(n)
        }
    },f=function(t,n,r,i,s,o){
        if(i==="out")var u=function(){
            t.afterCurrentFrameAnimatesOut();
            t.settings.transitionThreshold===!0&&(o===1?t.nextFrame.addClass("animate-in"):o===-1&&t.nextFrame.toggleClass("animate-out animate-in"))
        };
        else if(i==="in")var u=function(){
            t.afterNextFrameAnimatesIn();
            l(t);
            t.active=!1;
            if(!t.isHardPaused&&!t.isBeingHoveredOver)if(!t.delayUnpause)t.unpause(!1);
                else{
                    t.delayUnpause=!1;
                    t.unpause()
                }
        };
        r.data("animationEnded",!1);
        n.bind(t.transitionEnd,function(i){
            e(i.target).data("animationEnded",!0);
            var s=!0;
            r.each(function(){
                if(e(this).data("animationEnded")===!1){
                    s=!1;
                    return!1
                }
            });
            if(s){
                n.unbind(t.transitionEnd);
                u()
            }
        })
    },l=function(t){
        if(t.settings.hashTags){
            t.currentHashTag=t.nextFrame.prop(t.getHashTagFrom);
            t.frameHashIndex=e.inArray(t.currentHashTag,t.frameHashID);
            if(t.frameHashIndex!==-1&&(t.settings.hashChangesOnFirstFrame||!t.isStartingFrame||!t.transitionsSupported)){
                t.nextFrameID=t.frameHashIndex+1;
                document.location.hash="#"+t.currentHashTag
            }else{
                t.nextFrameID=t.settings.startingFrameID;
                t.isStartingFrame=!1
            }
        }
    },c={
        modernizr:function(){
            window.Modernizr=function(e,t,n){
                function r(e){
                    v.cssText=e
                }
                function i(e,t){
                    return r(prefixes.join(e+";")+(t||""))
                }
                function s(e,t){
                    return typeof e===t
                }
                function o(e,t){
                    return!!~(""+e).indexOf(t)
                }
                function u(e,t){
                    for(var r in e){
                        var i=e[r];
                        if(!o(i,"-")&&v[i]!==n)return t=="pfx"?i:!0
                    }
                    return!1
                }
                function a(e,t,r){
                    for(var i in e){
                        var o=t[e[i]];
                        if(o!==n)return r===!1?e[i]:s(o,"function")?o.bind(r||t):o
                    }
                    return!1
                }
                function f(e,t,n){
                    var r=e.charAt(0).toUpperCase()+e.slice(1),i=(e+" "+b.join(r+" ")+r).split(" ");
                    return s(t,"string")||s(t,"undefined")?u(i,t):(i=(e+" "+w.join(r+" ")+r).split(" "),a(i,t,n))
                }
                var l="2.6.1",c={},h=t.documentElement,p="modernizr",d=t.createElement(p),v=d.style,m,g={}.toString,y="Webkit Moz O ms",b=y.split(" "),w=y.toLowerCase().split(" "),E={
                    svg:"http://www.w3.org/2000/svg"
                },S={},x={},T={},N=[],C=N.slice,k,L={}.hasOwnProperty,A;
                !s(L,"undefined")&&!s(L.call,"undefined")?A=function(e,t){
                    return L.call(e,t)
                }:A=function(e,t){
                    return t in e&&s(e.constructor.prototype[t],"undefined")
                },Function.prototype.bind||(Function.prototype.bind=function(e){
                    var t=self;
                    if(typeof t!="function")throw new TypeError;
                    var n=C.call(arguments,1),r=function(){
                        if(self instanceof r){
                            var i=function(){};
                            i.prototype=t.prototype;
                            var s=new i,o=t.apply(s,n.concat(C.call(arguments)));
                            return Object(o)===o?o:s
                        }
                        return t.apply(e,n.concat(C.call(arguments)))
                    };
                    return r
                }),S.svg=function(){
                    return!!t.createElementNS&&!!t.createElementNS(E.svg,"svg").createSVGRect
                };
                for(var O in S)A(S,O)&&(k=O.toLowerCase(),c[k]=S[O](),N.push((c[k]?"":"no-")+k));return c.addTest=function(e,t){
                    if(typeof e=="object")for(var r in e)A(e,r)&&c.addTest(r,e[r]);else{
                        e=e.toLowerCase();
                        if(c[e]!==n)return c;
                        t=typeof t=="function"?t():t,enableClasses&&(h.className+=" "+(t?"":"no-")+e),c[e]=t
                    }
                    return c
                },r(""),d=m=null,c._version=l,c._domPrefixes=w,c._cssomPrefixes=b,c.testProp=function(e){
                    return u([e])
                },c.testAllProps=f,c.prefixed=function(e,t,n){
                    return t?f(e,t,n):f(e,"pfx")
                },c
            }(self,self.document)
        },
        defaultPreloader:function(t,n,r){
            var i='<div class="sequence-preloader"><svg class="preloading" xmlns="http://www.w3.org/2000/svg"><circle class="circle" cx="6" cy="6" r="6" /><circle class="circle" cx="22" cy="6" r="6" /><circle class="circle" cx="38" cy="6" r="6" /></svg></div>';
            e("head").append("<style>.sequence-preloader{height: 100%;position: absolute;width: 100%;z-index: 999999;}@"+r+"keyframes preload{0%{opacity: 1;}50%{opacity: 0;}100%{opacity: 1;}}.sequence-preloader .preloading .circle{fill: #ff9442;display: inline-block;height: 12px;position: relative;top: -50%;width: 12px;"+r+"animation: preload 1s infinite; animation: preload 1s infinite;}.preloading{display:block;height: 12px;margin: 0 auto;top: 50%;margin-top:-6px;position: relative;width: 48px;}.sequence-preloader .preloading .circle:nth-child(2){"+r+"animation-delay: .15s; animation-delay: .15s;}.sequence-preloader .preloading .circle:nth-child(3){"+r+"animation-delay: .3s; animation-delay: .3s;}.preloading-complete{opacity: 0;visibility: hidden;"+r+"transition-duration: 1s; transition-duration: 1s;}div.inline{background-color: #ff9442; margin-right: 4px; float: left;}</style>");
            t.prepend(i);
            if(!Modernizr.svg&&!n){
                e(".sequence-preloader").prepend('<div class="preloading"><div class="circle inline"></div><div class="circle inline"></div><div class="circle inline"></div></div>');
                setInterval(function(){
                    e(".sequence-preloader .circle").fadeToggle(500)
                },500)
            }else n||setInterval(function(){
                e(".sequence-preloader").fadeToggle(500)
            },500)
        },
        operaTest:function(){
            e("body").append('<span id="sequence-opera-test"></span>');
            var t=e("#sequence-opera-test");
            t.css("-o-transition","1s");
            return t.css("-o-transition")!=="1s"?!1:!0
        }
    },h={
        startingFrameID:1,
        cycle:!0,
        animateStartingFrameIn:!1,
        transitionThreshold:!1,
        reverseAnimationsWhenNavigatingBackwards:!0,
        preventDelayWhenReversingAnimations:!1,
        moveActiveFrameToTop:!0,
        autoPlay:!0,
        autoPlayDirection:1,
        autoPlayDelay:5e3,
        navigationSkip:!0,
        navigationSkipThreshold:250,
        fadeFrameWhenSkipped:!0,
        fadeFrameTime:150,
        preventReverseSkipping:!1,
        nextButton:!1,
        showNextButtonOnInit:!0,
        prevButton:!1,
        showPrevButtonOnInit:!0,
        pauseButton:!1,
        unpauseDelay:null,
        pauseOnHover:!0,
        pauseIcon:!1,
        showPauseButtonOnInit:!0,
        pagination:!1,
        showPaginationOnInit:!0,
        preloader:!1,
        preloadTheseFrames:[1],
        preloadTheseImages:[],
        hideFramesUntilPreloaded:!0,
        prependPreloadingComplete:!0,
        hidePreloaderUsingCSS:!0,
        hidePreloaderDelay:0,
        keyNavigation:!0,
        numericKeysGoToFrames:!0,
        keyEvents:{
            left:"prev",
            right:"next"
        },
        customKeyEvents:{},
        swipeNavigation:!0,
        swipeThreshold:20,
        swipePreventsDefault:!1,
        swipeEvents:{
            left:"prev",
            right:"next",
            up:!1,
            down:!1
        },
        hashTags:!1,
        hashDataAttribute:!1,
        hashChangesOnFirstFrame:!1,
        fallback:{
            theme:"slide",
            speed:500
        }
    };
    e.fn.sequence=function(t){
        return this.each(function(){
            e.data(this,"sequence")||e.data(this,"sequence",new n(e(this),t,h,c))
        })
    }
})(jQuery);