<?PHP
//所有数据操作(redis)相关的函数都放到这里来
/*
不能直接更改$glargv里的数据,需要通过update函数
updatedata函数在更改$glargv的某个元素的数据后,会在['commit']里记录一下
最大后请求完成后,利用commitdata()把['commit']里所有做的改变更新到redis 和 DB操作队列中
*/

/*
设置对($rid,  $table, $keyid) 的修改标志
有三种:update, insert ,delete,最后发送前,有一个commit的操作,把这些操作写入到redis中
*/
//设置$glargv['all']里的某个值,
//$glargv['all'][$rid]['rm_bagequip'][$keyid]= $value;
function updatedata($rid, $table, $keyid, $field, $value)
{
    updatedataarray($rid, $table, $keyid, array($field=>$value));
}

//一次设置一条记录的多个字段 变成多次设置不同的字段
//单行,多行要分别处理
//多行,没有这条记录就直接插入,单行,要判断是否默认值
//对于单行记录,redis,内存中是有默认值的,这个keyid是永远存在的,而数据库中是没有的,这个时候应该是insert

function updatedataarray($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;
    if(substr($table, 1, 1)== 'm')
    {
        //多行记录简单,没有就直接插入,
        if(isset($glargv['all'][$rid][$table][$keyid]))
        {
            foreach ($fields as $field=>$value)
            {
                //只有值修改了,才做update
                if($glargv['all'][$rid][$table][$keyid][$field] !== $value)
                {
                    //一次请求中可能修改了某个字段多次,则只记录第一次修改时的初始值
                    if(!isset($glargv['commit'][$rid]['oldvalue'][$table][$keyid][$field]))
                        $glargv['commit'][$rid]['oldvalue'][$table][$keyid][$field] = $glargv['all'][$rid][$table][$keyid][$field];
                    $glargv['all'][$rid][$table][$keyid][$field] = $value;
                    $glargv['commit'][$rid]['update'][$table][$keyid][$field]=$value;
                }
            }
        }
        else
            insertdataarray($rid, $table, $keyid, $fields);
    }
    else
    {
        //单行,内存中肯定是存在的.要判断是否默认值,靠字段 itime (insert time 来判断)
        if(!isset($glargv['all'][$rid][$table])
            or
           (isset($glargv['all'][$rid][$table][$keyid]['itime']) and $glargv['all'][$rid][$table][$keyid]['itime'] == 0))
        {
            $fields = array_merge($fields, array('itime'=>$glargv['nttime']));
            insertdataarray($rid, $table, $keyid, $fields);
        }
        else
        {
            foreach ($fields as $field=>$value)
            {
                //只有值修改了,才做update
                if($glargv['all'][$rid][$table][$keyid][$field] !== $value)
                {
                    //一次请求中可能修改了某个字段多次,则只记录第一次修改时的初始值
                    if(!isset($glargv['commit'][$rid]['oldvalue'][$table][$keyid][$field]))
                        $glargv['commit'][$rid]['oldvalue'][$table][$keyid][$field] = $glargv['all'][$rid][$table][$keyid][$field];
                    $glargv['all'][$rid][$table][$keyid][$field] = $value;
                    $glargv['commit'][$rid]['update'][$table][$keyid][$field]=$value;
                }
            }
        }
    }
}

//也是基于updatedata的两个包装,只包装数据增加
function adddata($rid, $table, $keyid, $field, $value)
{
    global $glargv;
    $value = abs($value);
    if(isset($glargv['all'][$rid][$table][$keyid]))
        $newvalue = $value + $glargv['all'][$rid][$table][$keyid][$field];
    else
        $newvalue = $value;
    updatedata($rid, $table, $keyid, $field, $newvalue);
    return $newvalue;
}

function subdata($rid, $table, $keyid, $field, $value)
{
    global $glargv;
    $value = abs($value);
    if(isset($glargv['all'][$rid][$table][$keyid]))
        $newvalue = $glargv['all'][$rid][$table][$keyid][$field] - $value;
    else
        $newvalue = -$value;
    if($newvalue >=0)
        updatedata($rid, $table, $keyid, $field, $newvalue);
    return $newvalue;
}

//插入都按照数组的方式来操作,只要只标记一次.
function insertdataarray($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;
    $row = $gltable[$table]['field']; //得到数据结构的初始值
    foreach ($fields as $field => $value)
        $row[$field] = $value;
    unset($row['rid']);
    unset($row['keyid']);
    $glargv['all'][$rid][$table][$keyid] = $row;
    $glargv['commit'][$rid]['insert'][$table][$keyid]=$row;
}

function insertdata($rid, $table, $keyid, $field, $value)
{
    insertdataarray($rid, $table, $keyid, array($field=>$value));
}

//这里的$valueold 基本只用在背包删除item上面,就是item的老的数量
//就是要把旧值带入到删除记录中去
function deletedata($rid, $table, $keyid, $valueold=0)
{
    global $glargv;
    if(isset($glargv['all'][$rid][$table][$keyid]))
    {
        //确实存在这个数据,才需要删除,否则pass
        unset($glargv['all'][$rid][$table][$keyid]);
    }
    $glargv['commit'][$rid]['delete'][$table][$keyid]=$valueold;
}

//把select的数据也写入到$glargv['commit'][$rid]['select']
//最后返回给 $datachange ,然后写入到 ['toc']['select']
function selectdata($rid, $table)
{
    global $glargv;
    $glargv['commit'][$rid]['select'][]=$table;
}

function commitdata($rid)
{
    if($rid>0) commitdata_one(0);
    return commitdata_one($rid);
}
//把内存数据变化写入到redis里面去和redis的mq中，然后返回修改的详细字段和数据
//对update,insert操作,对redis操作都是一样的,只是把内存的这一块直接写入redis中去.
//但对于写入到数据库,如果原来字段是array(),类型的,则需要encode
function commitdata_one($rid)
{
    //分别根据情况来操作
    //按照insert,delete,update的顺序来处理
    global $glargv;
    global $gltable;

    commitlog($rid, $glargv['commit'][$rid]);
    $rsinsert = array();
    if(isset($glargv['commit'][$rid]['delete']))
    {
        foreach ($glargv['commit'][$rid]['delete'] as $table=>$keylist)
        {
            foreach($keylist as $keyid=>$valueold)
            {
                $glargv['rediscon']->hdel($rid.':'.$table, $keyid);
                //插入sqlmq;
                sql2mq('delete', $table, array(), $rid, $keyid);
            }
        }
    }
    if(isset($glargv['commit'][$rid]['insert']))
    {
        foreach ($glargv['commit'][$rid]['insert'] as $table=>$keylist)
        {
            if(substr($table, 0, 3) == 'rs_')
                $rsinsert[] = $table;
            foreach($keylist as $keyid=>$fields)
            {
                $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($glargv['all'][$rid][$table][$keyid]));
                //插入sqlmq;有可能一次请求中,插入后,还update了数据,所以这块只能插入insert时的原始数据
                foreach($fields as $field=>$value)
                {//类型是数组,插入到db时候需要json_encode
                    if(is_array($gltable[$table]['field'][$field]))
                        $fields[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                sql2mq('insert', $table, $fields, $rid, $keyid);
            }
        }
    }
    if(isset($glargv['commit'][$rid]['update']))
    {
        foreach ($glargv['commit'][$rid]['update'] as $table=>$keylist)
        {
            foreach($keylist as $keyid=>$fields)
            {
                // debugvar($glargv['all'][$rid][$table][$keyid]);
                //更改覆写到redis中,是全行记录覆写
                $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($glargv['all'][$rid][$table][$keyid]));
                //插入sqlmq,只插入改变就可以了;
                foreach($fields as $field=>$value)
                {//类型是数组,插入到db时候需要json_encode
                    if(is_array($gltable[$table]['field'][$field]))
                        $fields[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                sql2mq('update', $table, $fields, $rid, $keyid);
            }
        }
    }
    //这里可以处理或者过滤一些不想发送给客户端的一些字段和数据
    //单行的insert, 对db来说是insert,对redis来说是直接设置
    //对toc来说,就是update了,因为所有的单行表,最开始的时候就已经push到客户端了.
    foreach ($rsinsert as $key => $value)
    {
        if(!isset($glargv['commit'][$rid]['update'][$value]))
            $glargv['commit'][$rid]['update'][$value] = $glargv['commit'][$rid]['insert'][$value];
        unset($glargv['commit'][$rid]['insert'][$value]);
    }
    //commit 后，把所有的修改字段返回
    if($rid>0)
        $ret = $glargv['commit'][$rid];
    else
        $ret = true;
    //最后清除操作过的标记
    unset($glargv['commit'][$rid]);
    return $ret;
}

//commit log ,专门来写commit的log,主要是前后数据的对比
//先lpush到mq中,然后logsvr读取出来,插入数据库log和文件log
//第一个是type,标识是普通的
function commitlog($rid, &$value)
{
    global $glargv;
    if(!is_null($value))
    {
        //@todo: 这个应该放到tasker中去完成
        $glargv['rediscon']->lpush('commitmq', sprintf('%d#%d#%f#%d#%d#%s',
                                                        1,
                                                        $glargv['reqid'],
                                                        $glargv['btime'],
                                                        $rid,
                                                        $glargv['commitmqid'],
                                                        json_encode($value, JSON_UNESCAPED_UNICODE))
                                    );
        $glargv['commitmqid']++;

    }
}

//gamesvr 结束提交这个, 然后根据rid, reqid, btime, 三者, 去update commitlog 插入的数据
//spendtime, 请求花费的ms
function commitspendtime($rid, $spendtime)
{
    global $glargv;
    $glargv['rediscon']->lpush('commitmq', sprintf('%d#%d#%f#%d#%d#%d',
                                                    2, //类型
                                                    $glargv['reqid'],
                                                    $glargv['btime'],
                                                    $rid,
                                                    0, //mqid 补位
                                                    floor($spendtime))
                                );
}

//买家购买物品, 卖家提取成功交易的美金时使用
function committradelog($value)
{
    global $glargv;
    if(!is_null($value))
    {
        //@todo: 这个应该放到tasker中去完成
        $glargv['rediscon']->lpush('commitmq', sprintf('%d#%d#%f#%d#%d#%s',
                                                        3, //类型
                                                        $glargv['reqid'],
                                                        $glargv['btime'],
                                                        0, //rid 补位
                                                        0, //mqid 补位
                                                        json_encode($value, JSON_UNESCAPED_UNICODE))
                                    );
    }
}

//sqlcmd 就三种:insert(replace), update delete
function sql2mq($sqlcmd, $table, $rowdata, $rid=0, $keyid=0)
{
    global $glargv;
    $pushsql = sprintf('%d#%d#%f#%d#%s#%s#%d#%d#%s',
        0, //$glargv['svrid'],svrid废除了, 但因为todb用到了这个位置, 所以暂时用0占位
        $glargv['reqid'],
        $glargv['btime'],
        $glargv['sqlmqid'],
        $sqlcmd,
        $table,
        $rid,
        $keyid,
        json_encode($rowdata, JSON_UNESCAPED_UNICODE));
    //@todo: 这个应该放到tasker中去完成
    $glargv['rediscon']->lpush('sqlmq', $pushsql);
    $glargv['sqlmqid']++;
    //@todo : 打个syslog
}


//所有的gamesvr的redis的hast操作,便于以后修改这里,全部修改到tasker去执行.
function redishset($rid, $table, $row, $keyid=0)
{
    //根据表名去判断是多行,还是单行表, 单行需要unset(rid), 多行还需要unset(keyid)
}

function redishdel($rid, $table, $keyid=0)
{
    //
}

function rediszadd()
{
    //专门做排序用的
}

//以上都是处理role数据在内存中的情况的,但role数据还有在redis+db,只在db的两种情况
//根据isset($glargv['session'][$rid])来判定role是否在内存中
function isinmemory($rid)
{
    global $glargv;
    if(isset($glargv['session'][$rid]))
        return true;
    else
        return false;
}

//根据redis的是否存在主键 $rid . ':session'  来判定role是否在redis中
function isinredis($rid)
{
    global $glargv;
    if($glargv['rediscon']->get($rid. ':session')===false)
        return false;
    else
        return true;
}

function extupdate($rid, $table, $keyid, $fields)
{
    if(substr($table, 1, 1) == 'm')
        return extupdatem($rid, $table, $keyid, $fields);
    else
        return extupdates($rid, $table, $rid, $fields);
}

function extinsert($rid, $table, $keyid, $fields)
{
    if(substr($table, 1, 1)== 'm')
        return extinsertm($rid, $table, $keyid, $fields);
    else
        return false;
}

function extdelete($rid, $table, $keyid)
{
    if(substr($table, 1, 1)== 'm')
        return extdeletem($rid, $table, $keyid);
    else
        return false;
}

//外部直接修改多行数据,比如从mgr的gm操作中
function extupdatem($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;

    if(substr($table, 1, 1)!= 'm')
        return false;
    $row = false;
    $inmemory = isinmemory($rid);
    $inredis = isinredis($rid);
    if($inmemory)
    {
        if(isset($glargv['all'][$rid][$table][$keyid]))
        {
            $row = $glargv['all'][$rid][$table][$keyid];
            //修改内存中的值
            foreach ($fields as $field=>$value)
                $glargv['all'][$rid][$table][$keyid][$field] = $value;
        }
        else
        {
            $row = $gltable[$table]['field'];
            foreach ($fields as $field=>$value)
                $row[$field] = $value;
            unset($row['rid']);
            unset($row['keyid']);
            $glargv['all'][$rid][$table][$keyid] = $row;
        }
    }
    elseif($inredis)
    {
        $oldstr = $glargv['rediscon']->hget($rid.':'.$table, $keyid);
        if($oldstr)
            $row = redis_decode($oldstr);
        else
            $row = array();
    }
    //改变值覆盖旧值,(或者或者覆盖默认值), 新值写入redis中
    if($row === false or count($row) == 0)
    {
        $row = $gltable[$table]['field'];
    }
    foreach ($fields as $field=>$value)
        $row[$field] = $value;
    unset($row['rid']);
    unset($row['keyid']);

    if($inmemory or $inredis)
    {
        $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
        //写到数据库中,这里就是用了replace来做修改所有字段??这里不能考虑replace(否则一不小心会覆盖原来的数据)
        foreach($row as $field=>$value)
        {//类型是数组,写入到db时候需要json_encode
            if(is_array($gltable[$table]['field'][$field]))
                $row[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        sql2mq('replace', $table, $row, $rid, $keyid);
    }
    else
    {
        //如果只存在数据库中,则只把fields字段修改push到数据库
        $row = array();
        foreach($fields as $field=>$value)
        {//类型是数组,写入到db时候需要json_encode
            if(is_array($gltable[$table]['field'][$field]))
                $row[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
            elseif(is_int($gltable[$table]['field'][$field]))
                $row[$field] = (int)$value;
            else
                $row[$field] = $value;
        }
        sql2mq('update', $table, $row, $rid, $keyid);
    }    
    return true;
}

function extinsertm($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;
    if(substr($table, 1, 1) != 'm')
        return false;
    $inmemory = isinmemory($rid);
    $inredis = isinredis($rid);
    //空数组,需要填上默认值
    $row = $gltable[$table]['field'];
    foreach ($fields as $field => $value)
        $row[$field] = $value;
    unset($row['rid']);
    unset($row['keyid']);

    if($table == 'gm_clanapply')
    {
        $glargv['all'][$rid][$table][$keyid] = $row;
        $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
    }
    else
    {
        if($inmemory)
            $glargv['all'][$rid][$table][$keyid] = $row;
        if($inmemory or $inredis)
            $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
    }
    foreach($row as $field=>$value)
    {//类型是数组,写入到db时候需要json_encode
        if(is_array($gltable[$table]['field'][$field]))
            $row[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    sql2mq('insert', $table, $row, $rid, $keyid);
    return true;
}

function extdeletem($rid, $table, $keyid)
{
    global $glargv;
    global $gltable;
    if(substr($table, 1, 1) != 'm')
        return false;
    $inmemory = isinmemory($rid);
    $inredis = isinredis($rid);
    if($table == 'gm_clanapply')
    {
        unset($glargv['all'][$rid][$table][$keyid]);
        $glargv['rediscon']->hdel($rid.':'.$table, $keyid);
    }
    else
    {
        if($inmemory)
            unset($glargv['all'][$rid][$table][$keyid]);
        if($inmemory or $inredis)
            $glargv['rediscon']->hdel($rid.':'.$table, $keyid);
    }
    //插入sqlmq;
    sql2mq('delete', $table, array(), $rid, $keyid);
    return true;
}

//单行数据修改但可能memory, redis,db中都不存在(靠itime来判断了)
//这里的keyid = rid(其实todb 中是不管这keyid的)
//单行就没有 insert, delete了.
function extupdates($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;
    if(substr($table, 1, 1) != 's')
        return false;
    $row = false;
    $inmemory = isinmemory($rid);
    $inredis = isinredis($rid);
    $nt = $glargv['nttime'];

    if($inmemory)
    {
        //修改内存中的值
        if(!isset($glargv['all'][$rid][$table])
            or
           $glargv['all'][$rid][$table][$keyid]['itime'] == 0)
        {
            //先用初始化值初始化内存
            $tmprow = $gltable[$table]['field'];
            unset($tmprow['rid']);
            unset($tmprow['keyid']);
            foreach ($tmprow as $field=>$value)
                $glargv['all'][$rid][$table][$keyid][$field] = $value;
            $fields = array_merge($fields, array('itime'=>$nt));
        }
        foreach ($fields as $field=>$value)
            $glargv['all'][$rid][$table][$keyid][$field] = $value;
    }
    elseif($inredis)
    {
        $oldstr = $glargv['rediscon']->hget($rid.':'.$table, $keyid);
        if($oldstr)
            $row = redis_decode($oldstr);
        else
            $row = $gltable[$table]['field'];
    }
    //准备复写到redis中
    if($row === false)
    {
        $row = $gltable[$table]['field'];
    }
    if(isset($gltable[$table]['field']['itime']) and $row['itime'] == 0)
    {
        $fields = array_merge($fields, array('itime'=>$nt));
    }
    foreach ($fields as $field=>$value)
        $row[$field] = $value;
    unset($row['rid']);
    unset($row['keyid']);
    if($inmemory or $inredis)
        $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
    //update进数据库,数据库有可能没有这个记录,所以先插入一条默认记录,再去修改
    if(isset($gltable[$table]['field']['itime']))
    {
        $initrow = $gltable[$table]['field'];
        unset($initrow['rid']);
        unset($initrow['keyid']);
        foreach($initrow as $field=>$value)
        {//类型是数组,写入到db时候需要json_encode
            if(is_array($gltable[$table]['field'][$field]))
                $initrow[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        sql2mq('insert', $table, $initrow, $rid, $keyid);
    }
    foreach($fields as $field=>$value)
    {//类型是数组,插入到db时候需要json_encode
        if(is_array($gltable[$table]['field'][$field]))
            $fields[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    sql2mq('update', $table, $fields, $rid, $keyid);
    return true;
}

//目前只有arena使用了这个函数
//不修改内存,只从redis里面的数据开始修改 ,这个时候肯定在redis中,而且肯定不是默认数据
function extupdatesredis($rid, $table, $keyid, $fields)
{
    global $glargv;
    global $gltable;
    if(substr($table, 1, 1) != 's')
        return false;
    $row = false;
    $nt = $glargv['nttime'];

    $oldstr = $glargv['rediscon']->hget($rid.':'.$table, $keyid);
    if($oldstr)
        $row = redis_decode($oldstr);
    else
        return false;
    if(isset($gltable[$table]['field']['itime']) and $row['itime'] == 0)
        return false;
    foreach ($fields as $field=>$value)
        $row[$field] = $value;
    unset($row['rid']);
    unset($row['keyid']);
    $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
    foreach($fields as $field=>$value)
    {//类型是数组,插入到db时候需要json_encode
        if(is_array($gltable[$table]['field'][$field]))
            $fields[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    sql2mq('update', $table, $fields, $rid, $keyid);
    return true;
}

//外部去删除背包里的东西
//简化成两种情况来处理, 在内存中的, 修改内存同时还要修改redis记录 
//不在内存中的, 直接删除redis中的session, 直接修改数据库,下次用户会被强制登录
//这里的keyid, 就是itemid, 这里的subvalue 就是要进去的值, 一般是1 
function extsubbagitem($rid, $keyid, $subvalue)
{
    global $glargv;

    $table = \bag\bagofitemid($keyid);
    $inmemory = isinmemory($rid);
    if($inmemory and isset($glargv['all'][$rid][$table][$keyid]))
    {
        $oldvalue = $glargv['all'][$rid][$table][$keyid]['num'];
        $value = max(0, $oldvalue - $subvalue);
        if($value == 0)
        {
            unset($glargv['all'][$rid][$table][$keyid]);
            $glargv['rediscon']->hdel($rid.':'.$table, $keyid);
            sql2mq('delete', $table, array(), $rid, $keyid);
        }
        else
        {
            $glargv['all'][$rid][$table][$keyid]['num'] = $value;
            $row = array('num'=>$value);
            $glargv['rediscon']->hset($rid.':'.$table, $keyid, redis_encode($row));
            sql2mq('update', $table, $row, $rid, $keyid);
        }
        //如果被抢夺方在内存中, 将需要reload的表,写到 redis->$rid:reflash 中
        $reflash = redis_decode($glargv['rediscon']->get($rid.':reflash'));
        if(!in_array($table, $reflash))
        {
            $reflash[] = $table;
            $glargv['rediscon']->set($rid.':reflash', redis_encode($reflash));
        }
    }
    else
    {
        //删除session, 用户必须重新登录了
        $glargv['rediscon']->del($rid. ':session');

        $row = array('subvalue'=>$subvalue);
        sql2mq('updatebag', $table, $row, $rid, $keyid);
    }
    return true;
}

//外部修改天梯积分$changepoint 可以为负数
function extchangeladderpoint($rid, $changepoint)
{
    global $glargv;

    $inmemory = isinmemory($rid);
    if($inmemory and isset($glargv['all'][$rid]['rs_ladder'][$rid]))
    {
        $oldvalue = $glargv['all'][$rid]['rs_ladder'][$rid]['point'];
        $value = max(0, $oldvalue + $changepoint);
        $glargv['all'][$rid]['rs_ladder'][$rid]['point'] = $value;
        $row = array('point'=>$value);
        $glargv['rediscon']->hset($rid.':rs_ladder', $rid, redis_encode($row));
        sql2mq('update', 'rs_ladder', $row, $rid, $rid);
    }
    else
    {
        //删除session, 用户必须重新登录了
        $glargv['rediscon']->del($rid. ':session');

        $row = array('changepoint'=>$changepoint);
        sql2mq('updateladder', 'rs_ladder', $row, $rid, $rid);
    }
    return true;

}

