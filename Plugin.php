<?php
/**
 * UnsplashLight - 全图片灯箱插件（稳定版）
 * @package UnsplashLight
 * @author Lan-Feng
 * @version 1.0.0
 * @link https://lonecho.com
 * @date 2024-01-01
 */
class UnsplashLight_Plugin implements Typecho_Plugin_Interface
{
    public static function activate() {
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'footer');
        
        return _t('UnsplashLight 2.3.0 激活成功');
    }

    public static function deactivate() {
        return _t('UnsplashLight 已停用');
    }

    public static function config(Typecho_Widget_Helper_Form $form) {
        // 图片质量设置
        $imageQuality = new Typecho_Widget_Helper_Form_Element_Select('imageQuality', 
            array(
                'full' => '全尺寸 (2400px)', 
                'regular' => '常规 (1600px)',
                'small' => '小尺寸 (800px)'
            ), 
            'regular', 
            _t('Unsplash图片质量')
        );
        $form->addInput($imageQuality);
        
        // 灯箱主题
        $theme = new Typecho_Widget_Helper_Form_Element_Select('theme', 
            array(
                'dark' => '深色主题',
                'light' => '浅色主题',
                'glass' => '毛玻璃效果'
            ), 
            'dark', 
            _t('灯箱主题风格')
        );
        $form->addInput($theme);
        
        // 排除的CSS类
        $excludeClasses = new Typecho_Widget_Helper_Form_Element_Text('excludeClasses', 
            NULL, 
            'avatar,thumbnail,emoji,icon,logo,button,profile,nolightbox', 
            _t('排除的CSS类'),
            _t('不添加灯箱效果的图片CSS类，用逗号分隔')
        );
        $form->addInput($excludeClasses);
        
        // 图片选择器
        $imageSelector = new Typecho_Widget_Helper_Form_Element_Text('imageSelector', 
            NULL, 
            'article img, .post-content img, .content img, .post-body img, .entry-content img', 
            _t('图片选择器'),
            _t('CSS选择器，指定哪些图片添加灯箱效果，用逗号分隔')
        );
        $form->addInput($imageSelector);
        
        // 延迟加载
        $lazyLoad = new Typecho_Widget_Helper_Form_Element_Radio('lazyLoad', 
            array(
                '1' => '启用',
                '0' => '禁用'
            ), 
            '1', 
            _t('延迟加载图片')
        );
        $form->addInput($lazyLoad);
        
        // 动画效果
        $animation = new Typecho_Widget_Helper_Form_Element_Radio('animation', 
            array(
                '1' => '启用',
                '0' => '禁用'
            ), 
            '1', 
            _t('过渡动画效果')
        );
        $form->addInput($animation);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    public static function header() {
        $settings = Helper::options()->plugin('UnsplashLight');
        $theme = isset($settings->theme) ? $settings->theme : 'dark';
        $animation = isset($settings->animation) ? intval($settings->animation) : 1;
        
        echo '<style>';
        
        // 基础样式
        echo '
        .lightbox-enabled { cursor: zoom-in !important; }
        .lightbox-disabled { cursor: default !important; }
        .unsplash-lightbox-overlay, .unsplash-lightbox-container {
            display: none; pointer-events: none; position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
        }
        .unsplash-lightbox-overlay { 
            background: rgba(0, 0, 0, 0.96); 
            z-index: 99999; 
            opacity: 0; 
            ' . ($animation ? 'transition: opacity 0.3s ease;' : '') . '
        }
        .unsplash-lightbox-container { 
            z-index: 100000; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            touch-action: none; 
        }
        .lightbox-active .unsplash-lightbox-overlay,
        .lightbox-active .unsplash-lightbox-container { 
            display: flex; 
            pointer-events: auto; 
        }
        .lightbox-active .unsplash-lightbox-overlay { 
            opacity: 1; 
        }

        .unsplash-lightbox-image-wrapper { 
            position: relative; 
            width: 100%; 
            height: 100%; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            cursor: grab; 
            overflow: hidden;
        }
        .unsplash-lightbox-image-wrapper:active { 
            cursor: grabbing; 
        }

        .unsplash-lightbox-image { 
            max-width: 90vw; 
            max-height: 85vh; 
            object-fit: contain; 
            opacity: 0; 
            transform: scale(1) translate(0px, 0px);
            ' . ($animation ? 'transition: opacity 0.3s ease;' : '') . '
            will-change: transform;
            user-select: none; 
            -webkit-user-drag: none;
        }
        .unsplash-lightbox-image.loaded { 
            opacity: 1; 
        }
        
        .unsplash-lightbox-counter { 
            position: absolute; 
            top: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            color: white; 
            background: rgba(0, 0, 0, 0.7); 
            padding: 8px 16px; 
            border-radius: 20px; 
            z-index: 100002; 
            font-size: 14px;
            backdrop-filter: blur(10px);
        }

        /* 按钮：使用 SVG 图标，几何精确居中 */
        .unsplash-lightbox-btn { 
            position: absolute; 
            border: none; 
            border-radius: 50%; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            ' . ($animation ? 'transition: background-color 0.2s ease;' : '') . '
            z-index: 100002;
            backdrop-filter: blur(10px);
            outline: none;
            padding: 0;
            margin: 0;
        }
        .unsplash-lightbox-close { 
            top: 20px; 
            right: 20px; 
            width: 44px; 
            height: 44px; 
        }
        .unsplash-lightbox-prev { 
            left: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            width: 50px; 
            height: 50px; 
        }
        .unsplash-lightbox-next { 
            right: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            width: 50px; 
            height: 50px; 
        }
        .unsplash-lightbox-btn svg {
            display: block;
            pointer-events: none;
        }
        ';
        
        // 主题样式
        switch($theme) {
            case 'light':
                echo '
                .unsplash-lightbox-overlay { background: rgba(255, 255, 255, 0.98); }
                .unsplash-lightbox-counter { color: #333; background: rgba(255, 255, 255, 0.9); }
                .unsplash-lightbox-btn { background: rgba(255, 255, 255, 0.9); color: #333; }
                .unsplash-lightbox-btn:hover { background: rgba(255, 255, 255, 0.95); }
                ';
                break;
            case 'glass':
                echo '
                .unsplash-lightbox-overlay { background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(20px); }
                .unsplash-lightbox-counter { background: rgba(255, 255, 255, 0.15); }
                .unsplash-lightbox-btn { background: rgba(255, 255, 255, 0.15); color: white; }
                .unsplash-lightbox-btn:hover { background: rgba(255, 255, 255, 0.25); }
                ';
                break;
            default: // dark theme
                echo '
                .unsplash-lightbox-btn { background: rgba(255, 255, 255, 0.15); color: white; }
                .unsplash-lightbox-btn:hover { background: rgba(255, 255, 255, 0.25); }
                ';
        }
        
        echo '</style>';
    }

    public static function footer() {
        $settings = Helper::options()->plugin('UnsplashLight');
        
        // 获取配置
        $q = 'regular';
        if (isset($settings->imageQuality)) {
            switch($settings->imageQuality) {
                case 'full': $q = 2400; break;
                case 'regular': $q = 1600; break;
                case 'small': $q = 800; break;
                default: $q = 1600;
            }
        }
        
        $lazyLoad = isset($settings->lazyLoad) ? intval($settings->lazyLoad) : 1;
        $excludeClasses = isset($settings->excludeClasses) ? trim($settings->excludeClasses) : 'avatar,thumbnail,emoji,icon,logo,button,profile,nolightbox';
        $imageSelector = isset($settings->imageSelector) ? trim($settings->imageSelector) : 'article img, .post-content img, .content img, .post-body img, .entry-content img';
        
        echo '<script>
        (function() {
            "use strict";
            
            var gallery = null;
            var state = { scale: 1, x: 0, y: 0, isDragging: false, startX: 0, startY: 0 };
            
            // 配置
            var config = {
                imageQuality: ' . $q . ',
                lazyLoad: ' . $lazyLoad . ',
                excludeClasses: "' . addslashes($excludeClasses) . '",
                imageSelector: "' . addslashes($imageSelector) . '"
            };

            function Lightbox() {
                this.isOpen = false;
                this.currentIndex = 0;
                this.images = [];
                this.doubleClickDetected = false;
                this.doubleClickTimer = null;
                this.clickCount = 0;
            }

            Lightbox.prototype = {
                init: function() {
                    this.createElements();
                    this.bindEvents();
                },
                
                createElements: function() {
                    // 创建灯箱元素
                    this.overlay = document.createElement("div");
                    this.overlay.className = "unsplash-lightbox-overlay";
                    this.overlay.setAttribute("role", "dialog");
                    this.overlay.setAttribute("aria-label", "图片灯箱");
                    
                    this.container = document.createElement("div");
                    this.container.className = "unsplash-lightbox-container";
                    
                    this.wrapper = document.createElement("div");
                    this.wrapper.className = "unsplash-lightbox-image-wrapper";
                    this.wrapper.setAttribute("role", "document");
                    
                    this.img = document.createElement("img");
                    this.img.className = "unsplash-lightbox-image";
                    this.img.setAttribute("alt", "灯箱图片");
                    this.img.setAttribute("loading", "lazy");
                    
                    // 信息元素
                    this.counter = document.createElement("div");
                    this.counter.className = "unsplash-lightbox-counter";
                    
                    // 按钮：使用 SVG 图标
                    this.closeBtn = document.createElement("button");
                    this.closeBtn.className = "unsplash-lightbox-btn unsplash-lightbox-close";
                    this.closeBtn.innerHTML = \'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>\';
                    this.closeBtn.setAttribute("aria-label", "关闭灯箱");
                    
                    this.prevBtn = document.createElement("button");
                    this.prevBtn.className = "unsplash-lightbox-btn unsplash-lightbox-prev";
                    this.prevBtn.innerHTML = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>\';
                    this.prevBtn.setAttribute("aria-label", "上一张图片");
                    
                    this.nextBtn = document.createElement("button");
                    this.nextBtn.className = "unsplash-lightbox-btn unsplash-lightbox-next";
                    this.nextBtn.innerHTML = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>\';
                    this.nextBtn.setAttribute("aria-label", "下一张图片");
                    
                    // 组装
                    this.wrapper.appendChild(this.img);
                    this.container.append(this.wrapper, this.counter, this.closeBtn, this.prevBtn, this.nextBtn);
                    document.body.append(this.overlay, this.container);
                },
                
                updateTransform: function() {
                    this.img.style.transform = "translate(" + state.x + "px, " + state.y + "px) scale(" + state.scale + ")";
                },
                
                resetState: function() {
                    state = { scale: 1, x: 0, y: 0, isDragging: false, startX: 0, startY: 0 };
                    this.updateTransform();
                },
                
                getImageCenter: function() {
                    var rect = this.img.getBoundingClientRect();
                    return {
                        x: rect.left + rect.width / 2,
                        y: rect.top + rect.height / 2
                    };
                },
                
                zoomAtMouse: function(e, delta) {
                    if (!this.isOpen) return;
                    
                    e.preventDefault();
                    
                    var mouseX = e.clientX;
                    var mouseY = e.clientY;
                    
                    var imgCenter = this.getImageCenter();
                    
                    var relativeX = mouseX - imgCenter.x;
                    var relativeY = mouseY - imgCenter.y;
                    
                    var newScale = Math.min(Math.max(1, state.scale + delta), 5);
                    
                    if (newScale === state.scale) return;
                    
                    var scaleFactor = (1 - newScale / state.scale);
                    var newX = state.x + relativeX * scaleFactor;
                    var newY = state.y + relativeY * scaleFactor;
                    
                    if (newScale === 1) {
                        newX = 0;
                        newY = 0;
                    }
                    
                    state.scale = newScale;
                    state.x = newX;
                    state.y = newY;
                    
                    this.updateTransform();
                },
                
                handleDoubleClick: function(e) {
                    if (!this.isOpen) return;
                    
                    if (this.doubleClickDetected) return;
                    this.doubleClickDetected = true;
                    
                    var self = this;
                    setTimeout(function() {
                        self.doubleClickDetected = false;
                    }, 300);
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var mouseX = e.clientX;
                    var mouseY = e.clientY;
                    var imgCenter = this.getImageCenter();
                    var relativeX = mouseX - imgCenter.x;
                    var relativeY = mouseY - imgCenter.y;
                    
                    if (state.scale > 1) {
                        state.scale = 1;
                        state.x = 0;
                        state.y = 0;
                    } else {
                        var newScale = 2.5;
                        var scaleFactor = (1 - newScale / state.scale);
                        state.x = relativeX * scaleFactor;
                        state.y = relativeY * scaleFactor;
                        state.scale = newScale;
                    }
                    this.updateTransform();
                },
                
                bindEvents: function() {
                    var self = this;
                    
                    this.closeBtn.onclick = function(e) { 
                        e.stopPropagation();
                        self.close(); 
                    };
                    
                    this.overlay.onclick = function(e) { 
                        if (e.target === self.overlay) {
                            self.close(); 
                        }
                    };
                    
                    this.prevBtn.onclick = function(e) { 
                        e.stopPropagation();
                        e.preventDefault();
                        if (self.doubleClickTimer) {
                            clearTimeout(self.doubleClickTimer);
                            self.doubleClickTimer = null;
                        }
                        self.prev(); 
                    };
                    
                    this.nextBtn.onclick = function(e) { 
                        e.stopPropagation();
                        e.preventDefault();
                        if (self.doubleClickTimer) {
                            clearTimeout(self.doubleClickTimer);
                            self.doubleClickTimer = null;
                        }
                        self.next(); 
                    };
                    
                    this.container.onwheel = function(e) {
                        var delta = e.deltaY > 0 ? -0.2 : 0.2;
                        self.zoomAtMouse(e, delta);
                    };
                    
                    this.img.ondblclick = function(e) {
                        self.handleDoubleClick(e);
                    };
                    
                    this.wrapper.onmousedown = function(e) {
                        if (state.scale <= 1) return;
                        
                        if (self.doubleClickTimer) {
                            clearTimeout(self.doubleClickTimer);
                            self.doubleClickTimer = null;
                        }
                        self.clickCount = 0;
                        
                        state.isDragging = true;
                        state.startX = e.clientX - state.x;
                        state.startY = e.clientY - state.y;
                    };
                    
                    var mouseMoveHandler = function(e) {
                        if (!state.isDragging) return;
                        state.x = e.clientX - state.startX;
                        state.y = e.clientY - state.startY;
                        self.updateTransform();
                    };
                    
                    var mouseUpHandler = function() { 
                        state.isDragging = false;
                    };
                    
                    window.addEventListener("mousemove", mouseMoveHandler);
                    window.addEventListener("mouseup", mouseUpHandler);
                    
                    var keydownHandler = function(e) {
                        if (!self.isOpen) return;
                        
                        switch(e.key) {
                            case "Escape":
                                self.close();
                                break;
                            case "ArrowLeft":
                                self.prev();
                                break;
                            case "ArrowRight":
                                self.next();
                                break;
                            case "+":
                            case "=":
                                if (e.ctrlKey || e.metaKey) {
                                    self.zoomAtMouse({clientX: window.innerWidth/2, clientY: window.innerHeight/2}, 0.5);
                                }
                                break;
                            case "-":
                                if (e.ctrlKey || e.metaKey) {
                                    self.zoomAtMouse({clientX: window.innerWidth/2, clientY: window.innerHeight/2}, -0.5);
                                }
                                break;
                            case "0":
                                if (e.ctrlKey || e.metaKey) {
                                    self.resetState();
                                }
                                break;
                        }
                    };
                    
                    document.addEventListener("keydown", keydownHandler);
                    
                    this._mouseMoveHandler = mouseMoveHandler;
                    this._mouseUpHandler = mouseUpHandler;
                    this._keydownHandler = keydownHandler;
                },
                
                open: function(index, images) {
                    this.images = images;
                    this.currentIndex = index;
                    this.isOpen = true;
                    this.resetState();
                    this.doubleClickDetected = false;
                    
                    if (this.doubleClickTimer) {
                        clearTimeout(this.doubleClickTimer);
                        this.doubleClickTimer = null;
                    }
                    this.clickCount = 0;
                    
                    document.body.classList.add("lightbox-active");
                    document.body.style.overflow = "hidden";
                    this.showImage();
                    
                    this.closeBtn.focus();
                },
                
                close: function() {
                    document.body.classList.remove("lightbox-active");
                    this.isOpen = false;
                    document.body.style.overflow = "";
                    
                    var self = this;
                    setTimeout(function() { 
                        self.img.src = "";
                        self.img.classList.remove("loaded");
                    }, 300);
                },
                
                showImage: function() {
                    var link = this.images[this.currentIndex];
                    var imgUrl = link.href;
                    var self = this;
                    
                    this.img.classList.remove("loaded");
                    this.resetState();
                    this.doubleClickDetected = false;
                    
                    this.counter.textContent = (this.currentIndex + 1) + " / " + this.images.length;

                    this.img.onload = function() {
                        self.img.classList.add("loaded");
                    };
                    
                    if (config.lazyLoad) {
                        setTimeout(function() {
                            self.img.src = imgUrl;
                        }, 10);
                    } else {
                        self.img.src = imgUrl;
                    }

                    this.prevBtn.style.display = this.currentIndex > 0 ? "flex" : "none";
                    this.nextBtn.style.display = this.currentIndex < this.images.length - 1 ? "flex" : "none";
                    
                    this.img.setAttribute("alt", "图片 " + (this.currentIndex + 1) + "，共" + this.images.length + "张");
                },
                
                prev: function() { 
                    if (this.currentIndex > 0) { 
                        this.currentIndex--; 
                        this.resetState();
                        this.showImage(); 
                    } 
                },
                
                next: function() { 
                    if (this.currentIndex < this.images.length - 1) { 
                        this.currentIndex++; 
                        this.resetState();
                        this.showImage(); 
                    } 
                },
                
                destroy: function() {
                    if (this._mouseMoveHandler) {
                        window.removeEventListener("mousemove", this._mouseMoveHandler);
                    }
                    if (this._mouseUpHandler) {
                        window.removeEventListener("mouseup", this._mouseUpHandler);
                    }
                    if (this._keydownHandler) {
                        document.removeEventListener("keydown", this._keydownHandler);
                    }
                }
            };

            function getUnsplashPhotoId(url) {
                if (!url) return null;
                
                var patterns = [
                    /\/photo-([a-zA-Z0-9\-_]{11,})[?\/]/,
                    /\/photo-([a-zA-Z0-9\-_]{11,})$/,
                    /\/photos\/([a-zA-Z0-9\-_]+)\//,
                ];
                
                for (var i = 0; i < patterns.length; i++) {
                    var match = url.match(patterns[i]);
                    if (match && match[1]) {
                        return match[1];
                    }
                }
                
                return null;
            }

            function shouldExcludeImage(img) {
                var excludeClasses = config.excludeClasses.split(",");
                var classList = img.className.split(" ");
                
                for (var i = 0; i < classList.length; i++) {
                    var className = classList[i].trim();
                    for (var j = 0; j < excludeClasses.length; j++) {
                        if (className === excludeClasses[j].trim()) {
                            return true;
                        }
                    }
                }
                
                if (img.naturalWidth && img.naturalWidth < 100) {
                    return true;
                }
                
                if (img.hasAttribute("data-no-lightbox") || img.hasAttribute("data-nolightbox")) {
                    return true;
                }
                
                var src = img.src || img.getAttribute("data-src") || "";
                if (src.toLowerCase().endsWith(".gif")) {
                    return true;
                }
                
                return false;
            }

            // ★ 收集"当前图片所在的 <article> 内"的灯箱链接，实现按卡片/文章分组切换
            function getScopedLinks(el) {
                var scope = el.closest("article") || document.body;
                var links = Array.from(scope.querySelectorAll(".lightbox-link"));
                // 兜底：如果 scope 内找不到（理论上不会），回退到全页面
                if (links.length === 0) {
                    links = Array.from(document.querySelectorAll(".lightbox-link"));
                }
                return links;
            }

            function processImage(img) {
                if (img.hasAttribute("data-lightbox-processed")) return;
                
                if (shouldExcludeImage(img)) {
                    img.classList.add("lightbox-disabled");
                    img.setAttribute("data-lightbox-processed", "true");
                    return;
                }
                
                var src = img.getAttribute("data-zoom-src")
                       || img.getAttribute("data-original")
                       || img.src
                       || img.getAttribute("data-src");
                
                if (!src) {
                    img.setAttribute("data-lightbox-processed", "true");
                    return;
                }
                
                var isUnsplash = src.indexOf("unsplash.com") !== -1;
                var link = document.createElement("a");
                link.className = "lightbox-link";
                link.setAttribute("role", "button");
                link.setAttribute("tabindex", "0");
                
                if (isUnsplash) {
                    var photoId = getUnsplashPhotoId(src);
                    if (photoId) {
                        link.href = "https://images.unsplash.com/photo-" + photoId + "?auto=format&fit=crop&w=" + config.imageQuality + "&q=85";
                    } else {
                        link.href = src;
                    }
                } else {
                    link.href = src;
                }
                
                img.classList.add("lightbox-enabled");
                
                var parent = img.parentNode;
                parent.replaceChild(link, img);
                link.appendChild(img);
                
                // ★ 点击：只收集当前 <article> 内的图片
                link.addEventListener("click", function(e) {
                    if (e.target.tagName === "IMG" || e.target === this) {
                        e.preventDefault();
                        var scoped = getScopedLinks(this);
                        var idx = scoped.indexOf(this);
                        if (idx < 0) idx = 0;
                        gallery.open(idx, scoped);
                    }
                });
                
                link.addEventListener("keydown", function(e) {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        var scoped = getScopedLinks(this);
                        var idx = scoped.indexOf(this);
                        if (idx < 0) idx = 0;
                        gallery.open(idx, scoped);
                    }
                });
                
                img.setAttribute("data-lightbox-processed", "true");
            }

            var init = function() {
                if (gallery) return;
                
                gallery = new Lightbox();
                gallery.init();
                
                var selectors = config.imageSelector.split(",");
                var processedImages = new Set();
                
                function processAllImages() {
                    selectors.forEach(function(selector) {
                        var selectorImages = document.querySelectorAll(selector.trim());
                        selectorImages.forEach(function(img) {
                            if (!processedImages.has(img)) {
                                processImage(img);
                                processedImages.add(img);
                            }
                        });
                    });
                }
                
                processAllImages();
                
                var observer = null;
                if (typeof MutationObserver !== "undefined") {
                    observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) {
                                    if (node.tagName === "IMG") {
                                        processImage(node);
                                    } else if (node.querySelectorAll) {
                                        var images = node.querySelectorAll("img");
                                        images.forEach(processImage);
                                    }
                                }
                            });
                        });
                        processAllImages();
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
                gallery._observer = observer;
                
                document.addEventListener("lazyloaded", function(e) {
                    if (e.target.tagName === "IMG") {
                        processImage(e.target);
                    }
                });
                
                setTimeout(processAllImages, 1000);
            };
            
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", init);
            } else {
                init();
            }
            
            window.UnsplashLight = {
                open: function(index) {
                    if (gallery) {
                        var links = Array.from(document.querySelectorAll(".lightbox-link"));
                        if (links.length > 0) {
                            gallery.open(Math.max(0, Math.min(index, links.length - 1)), links);
                        }
                    }
                },
                close: function() {
                    if (gallery) {
                        gallery.close();
                    }
                },
                destroy: function() {
                    if (gallery) {
                        gallery.destroy();
                        if (gallery._observer) {
                            gallery._observer.disconnect();
                        }
                        gallery = null;
                    }
                }
            };
        })();
        </script>';
    }
}