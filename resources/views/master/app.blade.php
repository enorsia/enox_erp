<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{config('app.name')}} | @yield('title', 'Admin')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Google Fonts (loaded here so CSS @import is not needed inside bundled CSS) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Play:wght@400;700&display=swap">

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

    {{-- Inline theme config — MUST run synchronously before first paint to prevent blink --}}
    <script>
        (function(){
            var html = document.documentElement;
            var saved = sessionStorage.getItem("__LARKON_CONFIG__");
            var def = { theme:"light", topbar:{color:"light"}, menu:{size:"sm-hover-active",color:"dark"} };
            var c = saved ? JSON.parse(saved) : JSON.parse(JSON.stringify(def));
            if(!saved){
                c.theme = html.getAttribute('data-bs-theme') || def.theme;
                c.topbar.color = html.getAttribute('data-topbar-color') || def.topbar.color;
                c.menu.color = html.getAttribute('data-menu-color') || def.menu.color;
                c.menu.size = html.getAttribute('data-menu-size') || def.menu.size;
            }
            window.defaultConfig = JSON.parse(JSON.stringify(def));
            window.config = c;
            html.setAttribute("data-bs-theme", c.theme);
            html.setAttribute("data-topbar-color", c.topbar.color);
            html.setAttribute("data-menu-color", c.menu.color);
            html.setAttribute("data-menu-size", window.innerWidth <= 1140 ? "hidden" : c.menu.size);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('css')
</head>

<body>
    <!-- START Wrapper -->
    <div class="wrapper">
        @include('master.header.topbar')
        @include('master.sidebar.index')
        <!-- ==================================================== -->
        <!-- Start right Content here -->
        <!-- ==================================================== -->
        <div class="page-content">
            <!-- Start Container Fluid -->
            <div class="container-xxl">

                @yield('content')

            </div>
            <!-- End Container Fluid -->
            @include('master.footer.index')
        </div>
        <!-- ==================================================== -->
        <!-- End Page Content -->
        <!-- ==================================================== -->

    </div>
    <!-- END Wrapper -->


    @include('master.lara-izitoast')


    <script>
    (function () {
        if (typeof window.jQuery !== 'undefined') return;
        var queue = [];
        function makeChain(selector) {
            var chain = {};
            ['ready','on','off','click','change','keyup','keydown','submit','each',
             'find','val','text','html','attr','data','trigger','prop','addClass',
             'removeClass','toggleClass','css','show','hide','append','prepend',
             'closest','parents','siblings','next','prev','children','parent',
             'serialize','serializeArray','empty','remove'].forEach(function (m) {
                chain[m] = function () {
                    var args = Array.from(arguments);
                    queue.push(function () {
                        try { var el = jQuery(selector); el[m].apply(el, args); } catch (e) {}
                    });
                    return chain;
                };
            });
            return chain;
        }
        function jqShim(arg) {
            if (typeof arg === 'function') { queue.push(arg); return; }
            return makeChain(arg);
        }
        jqShim.fn = {};
        jqShim.ajax = function (opts) { queue.push(function () { jQuery.ajax(opts); }); };
        jqShim.extend = function () {};
        window.$ = window.jQuery = jqShim;
        document.addEventListener('DOMContentLoaded', function () {
            window.$ = window.jQuery = jQuery;
            queue.forEach(function (fn) { try { fn(); } catch (e) { console.error(e); } });
        });
    })();
    </script>

    @stack('js')
</body>

</html>
