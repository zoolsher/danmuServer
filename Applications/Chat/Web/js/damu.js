function DANMU(domain,port,room_id,callback){
    if(!(this instanceof DANMU)){
        console.log('you are call this in a wrong way!!!!');
        return;
    }
    // if (typeof console == "undefined") {    
    //     this.console = { 
    //         log: function (msg) {  } 
    //     };
    // }

    this.WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";
    this.WEB_SOCKET_DEBUG = true;
    this.port = port;
    this.room_id = room_id;
    var ws;
    var client_list={};
    var self = this;
    // 连接服务端
    this.connect = function(){
       // 创建websocket
       this.ws = new WebSocket("ws://"+domain+":"+port);
       // 当socket连接打开时，输入用户名
       this.ws.onopen = onopen;
       // 当有消息时根据消息类型显示不同信息
       this.ws.onmessage = onmessage; 
       this.ws.onclose = function() {
    	  console.log("连接关闭，定时重连");
          self.connect();
       };
       this.ws.onerror = function() {
     	  console.log("出现错误");
       };
    }

    // 连接建立时发送登录信息
    function onopen()
    {
        var login_data = '{"type":"login","client_name":"'+name.replace(/"/g, '\\"')+'","room_id":'+room_id+'}';
        console.log("websocket握手成功，发送登录数据:"+login_data);
        this.send(login_data);
    }

    // 服务端发来消息时
    function onmessage(e)
    {
        // console.log(e.data);
        var data = eval("("+e.data+")");
        switch(data['type']){
            // 服务端ping客户端
            case 'ping':
                this.send('{"type":"pong"}');
                break;;
            // 登录 更新用户列表
            case 'login':
                //{"type":"login","client_id":xxx,"client_name":"xxx","client_list":"[...]","time":"xxx"}
                // callback( data['client_name']+' 加入了聊天室', data['time']);
                if(data['client_list'])
                {
                    client_list = data['client_list'];
                }
                else
                {
                    client_list[data['client_id']] = data['client_name']; 
                }
                
                console.log(data['client_name']+"登录成功");
                break;
            // 发言
            case 'say':
                //{"type":"say","from_client_id":xxx,"to_client_id":"all/client_id","content":"xxx","time":"xxx"}
                callback( client_list[data['from_client_id']], data['content'] );
                break;
            // 用户退出 更新用户列表
            case 'logout':
                //{"type":"logout","client_id":xxx,"time":"xxx"}
                // callback(data['from_client_id'], data['from_client_name'], data['from_client_name']+' 退出了', data['time']);
                delete client_list[data['from_client_id']];
        }
    }

    this.send = function(value){
        this.ws.send('{"type":"say","to_client_id":"all","to_client_name":"all","content":"'+value.replace(/"/g, '\\"').replace(/\n/g,'\\n').replace(/\r/g, '\\r')+'"}');
    }
}