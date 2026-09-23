FROM node:22-alpine

WORKDIR /app

# node_modules est géré par un volume Docker nommé (voir docker-compose.yml) :
# on ne copie donc pas package.json ici, `npm install` tourne au démarrage
# du conteneur si besoin (voir CMD), pour rester en phase avec le code monté
# en volume sans reconstruire l'image à chaque changement de dépendance.

EXPOSE 5173

CMD sh -c "[ -x node_modules/.bin/vite ] || npm install; npm run dev -- --host 0.0.0.0"
