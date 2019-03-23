<?php

/**
 * 基于LEVELDB存储K-V
 * 目前无法通过传入回调函数使GO语言注册运行时函数到ZEND中
 * 因此无法在PHP中进行异步阻塞编程
 * 另外，K-V数据传输时如果传入数组，则需要通过json_encode转换为字符串
 * @Author QiuXiangCheng
 * @DateTime 2019-03-24T02:06:04+0800
 */

class Demo {

    public $kv; // 扩展类库

    // leveldb 默认数据保存路径
    private $path = '';

    // 布隆过滤器（暂时去掉了~）
    private $bloomfilter = 0;

    /**
     * @path leveldb的数据保存路径
     * @Author   Mr.Q
     * @DateTime 2019-03-24T02:06:04+0800
     */
    public function __construct($path = '') {

        $this -> kv = new Tiky();
        $this -> kv -> Open($path); // 打开与关闭必须是要配套的
    }

    public function close() {

        $this -> kv -> Close();
    }

    /**
     * $k/$v 仅接受字符串类型
     * @Author   Mr.Q
     * @DateTime 2019-03-24T02:06:22+0800
     */
    public function set($k, $v) {

        if (!is_array($v)) {
            return $this -> kv -> Set($k, $v);
        }
        return $this -> kv -> Set($k, json_encode($v));
    }

    /**
     * 通过获取byte形式自主转换
     * @Author   Mr.Q
     * @DateTime 2019-03-24T02:06:29+0800
     */
    public function get($k) {

        if (count($buf = $this -> kv -> Get($k))) {
            return self::byte2str($buf);;
        }
        return "";
    }

    /**
     * 内部转换后输出
     * @Author   Mr.Q
     * @DateTime 2019-03-24T03:28:18+0800
     */
    public function getString($k) {

        return $this -> kv -> GetString($k);
    }

    public function del($k) {

        return $this -> kv -> Del($k);
    }

    // 将go语言输出的 []uint8 类型(byte类型)转为字符串
    private static function byte2str($buf) {

        $str = '';
        foreach ($buf as $item) {
            $str .= chr($item);
        }
        return $str;
    }

    /**
     * 根据键值模糊查询 批量返回byte数据
     * $limit 限定返回条数
     * @Author   Mr.Q
     * @DateTime 2019-03-24T03:29:46+0800
     */
    public function getIteratorByte($k, $limit = 10) {

        return $this -> kv -> SeekThenIterate($k, $limit);
    }

    /**
     * 根据键值模糊查询 批量返回字符串数据
     * @Author   Mr.Q
     * @DateTime 2019-03-24T03:31:30+0800
     */
    public function newIterator($k, $limit = 10) {

        $list = [];
        $buf = $this -> getIteratorByte($k, $limit);
        $len = count($buf);

        while (self::iteratorLoop($buf, $len, $list));
        return $list;
    }

    /**
     * 根据key前缀查询
     * @Author   Mr.Q
     * @DateTime 2019-03-24T03:49:12+0800
     */
    public function getLastByBytesPrefix($k, $limit = 10) {

        $list = [];
        $buf = $this -> kv -> GetListByKey($k, $limit);
        $len = count($buf);

        while (self::iteratorLoop($buf, $len, $list));
        return $list;
    }

    /**
     * 批量取出数据转换为字符串
     * @Author   Mr.Q
     * @DateTime 2019-03-24T03:53:48+0800
     */
    private static function iteratorLoop($buf, $len, &$list) {

        static $count = 0;
        if ($count >= $len) {
            return false;
        }
        $list[] = self::byte2str($buf[$count]);
        $count ++;

        return true;
    }

    public function __destruct() {

        $this -> close();
    }

    /**
     * 设置布隆过滤器加快leveldb查询速度 官方评测 速度提升大约1000倍
     * 但由于布隆过滤器会出现一定误差 需要结合实际场景运用
     */
    /*public function setBloomFilter($num = 10) {

        $this -> bloomfilter = $num;
        return $this;
    }*/

}

$db = new Demo();

$db -> set('test', 'wwww');
$db -> set('test', 'wwxww');
$db -> set('test', [111, 222, 333]);

$db -> get('test');       // get和getString是一样的 一个在内部转换 一个在外部
$db -> getString('test');

// $db -> del('test');

$buf = $db -> getLastByBytesPrefix('t');

print_r($buf);
