# SEE VOLUME 
docker volume ls
NOTE: FIND RELATED VOLUME AND REMOVE IT 
docker volume rm <VOLUME NAME>

docker compose -f docker-compose.dev.yml up -d --scale app=3


mysqldump -u enox_suite_db_user_2026 -p \
--single-transaction \
--routines \
--triggers \
enox_suite_db_2026 > enox_suite_db_2026.sql


scp ukenorsia@172.16.61.171:/srv/enox_erp/enox_suite_db_2026.sql $env:USERPROFILE\Downloads\


Redis DB,Function,Key Prefix,Why it’s needed for Scaling
DB 0,Default / Global,enoxsuite:defaultdb-,Catch-all for basic Redis commands and shared app state.
DB 1,Cache,enoxsuite:cache-,Stores heavy computation results so all N replicas don't have to re-run them.
DB 2,Sessions,enoxsuite:session-,Critical. Ensures a user stays logged in even if they jump from App_1 to App_5.
DB 3,Queues,enoxsuite:queue-,"Acts as the ""Post Office"" where your App containers drop jobs for your Worker containers."
DB 5,Event-Driven,eventdriven:,Dedicated space for real-time broadcasting or custom event logs.



docker compose up --build
docker compose up
docker compose up -d
docker compose down
docker exec -it enox_erp-nginx-1 sh
cat /etc/nginx/conf.d/default.conf

docker exec -it enox_erp-app-1 php artisan config:clear
docker exec -it enox_erp-app-1 php artisan cache:clear
docker exec -it enox_erp-db-1 mysql -u root -proot -e "show databases;"

docker exec -it enox_erp-redis-1 redis-cli
docker-compose restart app



Redis Session Test
docker-compose exec app php artisan tinker
session(['test_user' => 'Enox Learner']);
session()->save();
docker-compose exec redis redis-cli -n 2 keys "*"


docker compose -f docker-compose.dev.yml up -d



# Redis session tesing.
docker compose -f docker-compose.dev.yml down
docker compose -f docker-compose.dev.yml up -d
docker exec -it enox_erp-app-1 sh
php artisan config:clear
php artisan cache:clear
php artisan tinker
session(['test_user' => 'Enox Learner']);
session()->save();
session()->get('test_user')
exit()
exit()

docker exec -it enox_erp-redis-1 redis-cli
select 2
keys *

1) "enoxsuitesession-enoxsuite-cache-PUFQ6ZeejIMItGlnoonUZOeFBKdo124HeGkXEKDo"
2) "enoxsuitesession-enoxsuite-cache-4a9sMpxiDcdoRXfNEVebU9OXgRnmBRoDXejvQ2yp"
