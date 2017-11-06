ps aux |grep php |grep 4321|awk '{print $2}' | xargs -t -i{} kill -9 {}
ps aux |grep php |grep 4321|awk '{print $2}' | xargs -t -i{} kill -9 {}
nohup php vssvr.php &
sleep 1
ps aux |grep 4321
