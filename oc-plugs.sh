#!/usr/bin/env zsh

# Disable and uninstall OpenClaw plugins.

# openclaw plugins disable alibaba
# openclaw plugins uninstall alibaba

typeset -Ar rmList=( acpx amazon-bedrock amazon-bedrock-mantle anthropic \
  anthropic-vertex arcee bluebubbles bonjour byteplus chutes \
  cloudflare-ai-gateway comfy copilot-proxy deepseek fal fireworks \
  github-copilot kilocode kimi microsoft microsoft-foundry minimax mistral \
  moonshot nvidia openai opencode opencode-go openrouter mistral perplexity \
  phone-control qianfan runway sglang synthetic tencent together venice \
  vercel-ai-gateway volcengine voyage vydra xai xiaomi zai )

typeset -Ar aList=( discord duckduckgo firecrawl openshell webhooks whatsapp )

#echo "Removing qqbot."
#openclaw plugins disable qqbot
#openclaw plugins uninstall --force qqbot
#openclaw plugins uninstall --force alibaba
#openclaw gateway restart

for plugin in $rmList; do
  echo "\nDisabling $plugin."
  openclaw plugins disable $plugin
  echo "\nUninstalling $plugin."
  openclaw plugins uninstall --force $plugin
done

openclaw gateway restart

for plugin in "$aList"; do
  echo "\nAdding $plugin."
  openclaw plugins enable $plugin
done
