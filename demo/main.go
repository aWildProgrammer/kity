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

/**
 * 根据key前缀查询
 */
func (this *TLevelDB) GetListByKey(k string, limit int) [][]byte {

	count := 0
	buf   := make([][]byte, 0, 100)
	iter  := this.Db.NewIterator(util.BytesPrefix([]byte(k)), nil)
	for iter.Next() {
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


func (this *TLevelDB) Close() {

	this.Db.Close()
}
















func init() {
	Print()
	log.Println("run us init...")
	rand.Seed(time.Now().UnixNano())

	phpgo.InitExtension("pg0", "")
	phpgo.RegisterInitFunctions(module_startup, module_shutdown, request_startup, request_shutdown)

	// test global vars
	{
		modifier := func(ie *zend.IniEntry, newValue string, stage int) int {
			log.Println(ie.Name(), newValue, stage)
			return 0
		}
		displayer := func(ie *zend.IniEntry, itype int) {
			log.Println(ie.Name(), itype)
		}
		phpgo.AddIniVar("pg0.h", 567, false, modifier, displayer)
		phpgo.AddIniVar("pg0.k", 832, true, modifier, displayer)
	}
	
	// phpgo.AddFunc("Sett", Sett)
	// phpgo.AddFunc("Gett", Gett)

	phpgo.AddClass("Tiky", NewTLevelDB)
	// zend.AddMethod("TLevelDB", "Setk", TLevelDB.Setk)
	// zend.AddMethod("TLevelDB", "Getk", TLevelDB.Getk)

}

// should not run this function
func main() { panic("wtf") }

func module_startup(ptype int, module_number int) int {
	return rand.Int()
}
func module_shutdown(ptype int, module_number int) int {
	return rand.Int()
}
func request_startup(ptype int, module_number int) int {
	return rand.Int()
}
func request_shutdown(ptype int, module_number int) int {
	return rand.Int()
}