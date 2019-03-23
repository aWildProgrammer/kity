# kity
##### 通过Go语言扩展包封装实现一个以PHP调用LevelDB的动态类库，其中引用了 https://github.com/kitech/php-go 的PHP扩展类，并做一些修改。
##### 在此基础上，融合谷歌的GO语言版LevelDB实现持久化存储;

```
安装方式：
cd $GOPATH/gihub.com/kitech/php-go
make // 在make之前确保你的php-config文件是MakeFile文件中的路径
mv -f examples.so /usr/local/php.xxxxxx/lib/php/extensions/no-debug-non-zts-xxxxxx/
php.ini文件引入examples.so
```

```php
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
```
```go
package main

import ."fmt"
import "log"
import "math/rand"
import "time"
import "github.com/kitech/php-go/phpgo"
import "github.com/kitech/php-go/zend"
import "github.com/syndtr/goleveldb/leveldb"
// import "github.com/syndtr/goleveldb/leveldb/opt"
// import "github.com/syndtr/goleveldb/leveldb/filter"
import "github.com/syndtr/goleveldb/leveldb/util"

const default_db_path = "data/log"

type TLevelDB struct {
	Db   *leveldb.DB
}

func NewTLevelDB() *TLevelDB {

	return &TLevelDB {}
}

func (this *TLevelDB) Open(path string) *TLevelDB {

	if path == "" {
		path = default_db_path
	}
	this.Db, _ = leveldb.OpenFile(path, nil)
	return this
}

func (this *TLevelDB) Set(k, v string) bool {

	if err := this.Db.Put([]byte(k), []byte(v), nil); err != nil {
		return false
	}
	return true
}

func (this *TLevelDB) Get(k string) []uint8 {

	data, _ := this.Db.Get([]byte(k), nil)
	return data
}

func (this *TLevelDB) GetString(k string) string {

	return string(this.Get(k))
}

func (this *TLevelDB) Del(k string) bool {

	if err := this.Db.Delete([]byte(k), nil); err != nil {
		return false
	}
	return true
}

/**
 * 根据key批量查询 迭代输出
 */
func (this *TLevelDB) SeekThenIterate(k string, limit int) [][]byte {

	// buf := &bytes.Buffer{}
	count := 0
	buf   := make([][]byte, 0, 100)
	iter  := this.Db.NewIterator(nil, nil)

	for ok := iter.Seek([]byte(k)); ok; ok = iter.Next() {
		count ++
		buf = append(buf, iter.Value())
		if count >= limit {
			break
		}
	}

	iter.Release()
	iter.Error()

	return buf
}

```



demo 文件： https://github.com/aWildProgrammer/kity/tree/master/demo
