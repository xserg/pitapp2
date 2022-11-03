@extends('layouts.abstract')
@section('styles')
    <!-- Angular Material style sheet -->
    <link href="/css/font-awesome.css" rel="stylesheet" media="screen">
    <link href="/css/public.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/angular-material/1.1.0-rc.5/angular-material.min.css" />
    <link href="//fonts.googleapis.com/css?family=Roboto:500,400,300" rel="stylesheet" type="text/css" />

@overwrite
@section('scripts')
    <!-- Fonts -->
    <script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.16/webfont.js"></script>
    <script>
        WebFont.load({
            google: {
                families: ['Lato:300,400,500,600,900']
            }
        });
    </script>
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js" defer></script>
    <script src="/js/public-app.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.3/jquery-ui.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.10.5/xlsx.full.min.js" defer> </script>
@overwrite
