  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="google" content="notranslate" />
  <meta http-equiv="Content-Language" content="en">
  
  <meta name="AUTHOR"      content="Ryan Welling, Blake Burton, Zane Zakraisek">
  <meta name="keywords"    content="University of Utah, 2017-2018, College of Engineering">
  <meta name="description" content="Senior Project">
  <meta name="theme-color" content="#646a72">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!--App Manifest-->
  <link rel="manifest" href="./resources/manifest.json">  

  <!--U icon for browser tabs-->
  <link rel="icon" type="image/png" href="./resources/img/favicon-32x32.png">
  <link rel="icon" type="image/png" href="./resources/img/favicon-16x16.png">
  <link rel="icon" type="image/png" href="./resources/img/favicon.ico">

  <!-- ALL CSS FILES -->
  <?php
    $include   = './resources/CSS/global.css';
    $filemtime = filemtime($include);
    $source    = $include.'?ver='.$filemtime;
    echo "<link rel='stylesheet' type='text/css' href='".$source."'>";
  ?>

  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

  <!-- jQuery CDN -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js" integrity="sha384-vk5WoKIaW/vJyUAd9n/wmopsmNhiy+L2Z+SBxGYnUkunIxVxAv/UtMOhba/xskxh" crossorigin="anonymous"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" integrity="sha384-Dziy8F2VlJQLMShA6FHWNul/veM9bCkRUaLqr199K94ntO5QUrLJBEbYegdSkkqX" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css" integrity="sha384-Nlo8b0yiGl7Dn+BgLn4mxhIIBU6We7aeeiulNCjHdUv/eKHx59s3anfSUjExbDxn" crossorigin="anonymous">

  <!-- Latest compiled and minified JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
  <!-- Cloudflare buttons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">