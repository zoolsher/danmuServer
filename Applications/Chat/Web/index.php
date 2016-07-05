<html><head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>workerman-chat PHP聊天室 Websocket(HTLM5/Flash)+PHP多进程socket实时推送技术</title>
  <script type="text/javascript">
  //WebSocket = null;
  </script>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/style.css" rel="stylesheet">
  <!-- Include these three JS files: -->
  <script type="text/javascript" src="/js/swfobject.js"></script>
  <script type="text/javascript" src="/js/web_socket.js"></script>
  <script type="text/javascript" src="/js/jquery.min.js"></script>
  <script type="text/javascript" src="/js/damu.js"></script>
  <script type="text/javascript">


    var dm = new DANMU(document.domain,7272,1,function( name,content){
      console.log(name,content);
    });
    dm.connect();
    console.log(dm);


    // 提交对话
    function onSubmit() {
      var input = document.getElementById("content");
      dm.send(input.value);
      input.value = "";
      input.focus();
    }

  </script>
</head>
<body onload="">
  <input type="text" id="content">
  <button onclick="onSubmit()">tijiao</button>
</body>
</html>
