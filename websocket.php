<?php
// 创建一个table表用来共享内存数据
$table = new Swoole\Table(1024);
$table->column('name', swoole_table::TYPE_STRING, 32);
$table->column('rid', swoole_table::TYPE_INT, 10);
$table->column('isLive', swoole_table::TYPE_INT, 1);
$table->create();

// 实例化websocket服务
$server = new swoole_websocket_server('0.0.0.0', 8080, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$server->table = $table;

$server->set([
    // 守护进程
    'daemonize' => 1,
    // 指定错误日志文件
    'log_file' => './swoole.log'
]);

//监听连接事件
$server->on('open', function ($server, $request) {
    // $server->table->set($request->fd, ['fd'=>'用户'.$request->fd]);
});

//监听接收消息事件
$server->on('message', function ($server, $frame) {
    $data = json_decode($frame->data, true);

    if (isset($data['live'])) {
        var_dump($data);
        // echo '直播' . PHP_EOL;
        // 直播
        $info = $server->table->get($frame->fd);
        foreach ($server->table as $fd => $v) {
            if ($v['rid'] == $info['rid'] && $v['isLive'] == 1 && $fd != $frame->fd) {
                if ($server->isEstablished($fd)) {
                    $server->push($fd, json_encode(['live' => $data['live']]));
                }
            }
        }
    } else if (isset($data['disConnetToLive'])) {
        // 离开直播
        $info = $server->table->get($frame->fd);
        $info['isLive'] = 0;
        $server->table->set($frame->fd, $info);
    } else if (isset($data['connetToLive'])) {
        // 进入直播
        $info = $server->table->get($frame->fd);
        $info['isLive'] = 1;
        $server->table->set($frame->fd, $info);
    } else if (isset($data['name']) && isset($data['room'])) {
        // 进入房间，保存用户名及房间号
        $server->table->set($frame->fd, ['name' => $data['name'],'rid'=>$data['room'], 'isLive'=>0]);
        // 向所有用户推送消息
        pushToUser($server, '欢迎'.$data['name']. '进入本房间', $data['room']);
    } else if (isset($data['fd'])) {
        $user = $server->table->get($frame->fd);
        
        if ($data['fd'] > 0) {
            // 发送私聊
            // 获取被发送私聊人的姓名
            $toUser = $server->table->get($data['fd']);
            $msg = [
                $user['name'] . '对你私聊说：' . $data['info'],
                '你对'.$toUser['name'].'私聊说：' . $data['info']
            ];
        } else {
            // 群发
            $msg = $user['name'] . '对大家说：' . $data['info'];
        }
        pushToUser($server, $msg, $user['rid'], $data['fd'], $frame->fd);
    }
});

//监听关闭事件
$server->on('close', function ($server, $fd) {
    $user = $server->table->get($fd);
    $server->table->del($fd);
    pushToUser($server, $user['name'] . '离开房间', $user['rid']);
});

//开启服务
$server->start();

/**
 * 发送消息
 * @author   cinob
 * @dateTime 2019-07-22
 * @param    $server
 * @param    $msg   
 * @param    $rid   
 * @param    $userId
 * @param    $from  
 * @return   void
 */
function pushToUser($server, $msg, $rid, $userId = 0, $from = 0): void
{
    if ($userId) {
        echo $msg[0] . PHP_EOL;//打印到我们终端
        echo $msg[1] . PHP_EOL;//打印到我们终端
        if ($server->isEstablished($userId) && $server->isEstablished($from)) {
            $server->push($userId, json_encode(['no' => $userId, 'msg' => $msg[0], 'userList' => []]));
            $server->push($from , json_encode(['no' => $from, 'msg' => $msg[1], 'userList' => []]));
        }
    } else {
        echo $msg . PHP_EOL;//打印到我们终端
        $users = [];
        foreach ($server->table as $fd => $v) {
            if ($v['rid'] == $rid) {
                $users[$fd] = [
                    'fd' => $fd,
                    'name' => $v['name']
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
            if ($server->isEstablished($v['fd'])) {
                $server->push($v['fd'], json_encode(['no' => $v['fd'], 'msg' => $msg, 'userList' => array_values($list)]));
            }
        }
    }
}
