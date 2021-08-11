#!/usr/bin/env bash

IFS=$'\n' read -ra services -d $'\0' <<<"$(docker-compose config --services)"

delete=(traefik)

for target in "${delete[@]}"; do
  for serviceIndex in "${!services[@]}"; do
    if [[ ${services[serviceIndex]} = "${target}" ]]; then
      unset 'services[serviceIndex]'
    fi
  done
done

cat << EOF > docker-compose.override.yml
version: "3.8"
services:
EOF

for service in "${services[@]}"; do
  cat << EOF >> docker-compose.override.yml
  ${service}:
    entrypoint:
      - tail
    command:
      - "-f"
      - "/var/log/faillog"
EOF
done

docker-compose stop
docker-compose up -d

for service in "${services[@]}"; do
  printf "\n############################################ update %s ############################################\n" "${service}"
  docker-compose exec -T "${service}" bash -c "rm -rf var/*"
  docker-compose exec -T "${service}" bash -c "chmod -R 777 var"
  docker-compose exec -T "${service}" composer self-update --preview
  docker-compose exec -T "${service}" symfony self-update --yes
  docker-compose exec -T "${service}" bash -c "chmod -R 777 var"
  docker-compose exec -T "${service}" rm -rf vendor
  docker-compose exec -T "${service}" composer install
  docker-compose exec -Td "${service}" composer server:start:dev
  if [[ "mailer" != "${service}" ]]; then
    i=0
    while [[ $(docker-compose ps "${service}" | cut -d$'\n' -f 3 | cut -d' ' -f 10) != '(healthy)' && ${i} != 10 ]]; do
      printf ">> \e[21m\e[32mwait %s service to come healthy its %s, iteration %s\n\e[0m" "${service}" "$(docker-compose ps "${service}" | cut -d$'\n' -f 3 | cut -d' ' -f 10)" ${i}
      docker-compose ps "${service}" | cut -d$'\n' -f 3
      sleep 10
      i=$((i+1))
    done
  fi
done

rm docker-compose.override.yml
docker-compose up -d
docker-compose ps
