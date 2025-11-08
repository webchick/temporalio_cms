# Simple dev-friendly image for the Temporal worker + REST proxy
FROM node:20-bullseye

WORKDIR /app

# Install deps first to leverage Docker cache
COPY package*.json tsconfig.json .env.example ./
RUN npm install

# Copy source
COPY src ./src

ENV NODE_ENV=development

CMD ["npm", "run", "dev"]
