ps aux |grep php |grep platform|awk '{print $2}' | xargs -t -i{} kill -9 {}
ps aux |grep php |grep platform|awk '{print $2}' | xargs -t -i{} kill -9 {}
./s.sh
