<?php
// //创建websocket服务器对象，监听0.0.0.0:9502端口
// $ws = new swoole_websocket_server("0.0.0.0", 9502);

// //监听WebSocket连接打开事件
// $ws->on('open', function ($ws, $request) {
//     var_dump($request->fd, $request->get, $request->server);
//     $ws->push($request->fd, "hello, welcome\n");
// });

// //监听WebSocket消息事件
// $ws->on('message', function ($ws, $frame) {
//     echo "Message: {$frame->data}\n";
//     $ws->push($frame->fd, "server: {$frame->data}");
// });

// //监听WebSocket连接关闭事件
// $ws->on('close', function ($ws, $fd) {
//     echo "client-{$fd} is closed\n";
// });

// $ws->start();

class Chat
{
    const HOST = '0.0.0.0';//ip地址 0.0.0.0代表接受所有ip的访问
    const PART = 8080;//端口号
    private $server = null;//单例存放websocket_server对象
    private $connectList = [];//客户端的id集合

    public function __construct()
    {
        //实例化swoole_websocket_server并存储在我们Chat类中的属性上，达到单例的设计
        $this->server = new swoole_websocket_server(self::HOST, self::PART);
        //监听连接事件
        $this->server->on('open', [$this, 'onOpen']);
        //监听接收消息事件
        $this->server->on('message', [$this, 'onMessage']);
        //监听关闭事件
        $this->server->on('close', [$this, 'onClose']);
        //设置允许访问静态文件
        // $this->server->set([
        //     'document_root' => '/grx/swoole/public',//这里传入静态文件的目录
        //     'enable_static_handler' => true//允许访问静态文件
        // ]);
        //开启服务
        $this->server->start();
    }

    /**
     * 连接成功回调函数
     * @param $server
     * @param $request
     */
    public function onOpen($server, $request)
    {
        echo $request->fd . '连接了' . PHP_EOL;//打印到我们终端
        $this->connectList[] = $request->fd;//将请求对象上的fd，也就是客户端的唯一标识，可以把它理解为客户端id，存入集合中
    }

    /**
     * 接收到信息的回调函数
     * @param $server
     * @param $frame
     */
    public function onMessage($server, $frame)
    {
        echo $frame->fd . '来了，说：' . $frame->data . PHP_EOL;//打印到我们终端
        //将这个用户的信息存入集合
        foreach ($this->connectList as $fd) {//遍历客户端的集合，拿到每个在线的客户端id
            //将客户端发来的消息，推送给所有用户，也可以叫广播给所有在线客户端
            $server->push($fd, json_encode(['no' => $frame->fd, 'msg' => $frame->data]));
        }
    }

    /**
     * 断开连接回调函数
     * @param $server
     * @param $fd
     */
    public function onClose($server, $fd)
    {
        echo $fd . '走了' . PHP_EOL;//打印到我们终端
        $this->connectList = array_diff($this->connectList, [$fd]);//将断开了的客户端id，清除出集合
    }
}

$obj = new Chat();