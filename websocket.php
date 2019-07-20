<?php
$table = new Swoole\Table(1024);
$table->column('fd', swoole_table::TYPE_STRING, 32);
$table->column('rid', swoole_table::TYPE_INT, 10);
$table->create();

$server = new swoole_websocket_server('0.0.0.0', 8080);
$server->table = $table;

//监听连接事件
$server->on('open', function ($server, $request) {
    // $server->table->set($request->fd, ['fd'=>'用户'.$request->fd]);
});

//监听接收消息事件
$server->on('message', function ($server, $frame) {
    $data = json_decode($frame->data, true);
    if (isset($data['name']) && isset($data['room'])) {
        $server->table->set($frame->fd, ['fd' => $data['name'],'rid'=>$data['room']]);
        pushToUser($server, '欢迎'.$data['name']. '进入本房间', $data['room']);
    } else if (isset($data['fd'])) {
        $user = $server->table->get($frame->fd);
        if ($data['fd'] > 0) {
            $msg = [
                $user['fd'] . '对你私聊说：' . $data['info'],
                '你对'.$user['fd'].'私聊说：' . $data['info']
            ];
        } else {
            $msg = $user['fd'] . '对大家说：' . $data['info'];
        }
        pushToUser($server, $msg, $user['rid'], $data['fd'], $frame->fd);
    }
});

//监听关闭事件
$server->on('close', function ($server, $fd) {
    $user = $server->table->get($fd);
    $server->table->del($fd);
    pushToUser($server, $user['fd'] . '离开房间', $user['rid']);
});

//开启服务
$server->start();

function pushToUser($server, $msg, $rid, $userId = 0, $from = 0) {
    if ($userId) {
        echo $msg[0] . PHP_EOL;//打印到我们终端
        echo $msg[1] . PHP_EOL;//打印到我们终端
        $server->push($userId, json_encode(['no' => $userId, 'msg' => $msg[0], 'userList' => []]));
        $server->push($from , json_encode(['no' => $from, 'msg' => $msg[1], 'userList' => []]));
    } else {
        echo $msg . PHP_EOL;//打印到我们终端
        $users = [];
        foreach ($server->table as $fd => $v) {
            if ($v['rid'] == $rid) {
                $users[$fd] = [
                    'fd' => $fd,
                    'name' => $v['fd']
                ];
            }
        }
        foreach ($users as $v) {
            $list = $users;
            unset($list[$v['fd']]);
            $list[0] = $v;
            ksort($list);
            // var_dump($list);
            //将客户端发来的消息，推送给所有用户，也可以叫广播给所有在线客户端
            $server->push($v['fd'], json_encode(['no' => $v['fd'], 'msg' => $msg, 'userList' => array_values($list)]));
        }
    }
}
