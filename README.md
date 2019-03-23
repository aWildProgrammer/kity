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
// 实例化扩展包
$db = new Demo();

// 设置键值
$db -> set('test', 'wwww');
$db -> set('test', 'wwxww');
$db -> set('test', [111, 222, 333]);

// get和getString是一样的 一个在内部转换 一个在外部
$db -> get('test');       
$db -> getString('test');

// $db -> del('test');

// 循环查找
$buf = $db -> getLastByBytesPrefix('t');

print_r($buf);
```
demo 文件： https://github.com/aWildProgrammer/kity/tree/master/demo
