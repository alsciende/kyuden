/// <reference types="vite/client" />

interface ImportMetaEnv {
  // the release, set when the production image is built (docker/production/Dockerfile)
  readonly VITE_KYUDEN_VERSION?: string
  // the invitation to the official Discord server, linked from the footer (docker/production/Dockerfile)
  readonly VITE_DISCORD_URL?: string
}
