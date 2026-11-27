# Rostrum — AI Models & Integration Update PRD

## 1. Objective

Update Rostrum's existing AI infrastructure to use the selected models and providers for AI reasoning, speech-to-text (STT), and text-to-speech (TTS).

The implementation must prioritize transcription accuracy, natural speech quality, low latency, reliability, and cost efficiency.

## 2. Approved AI Model Configuration

| Feature | Primary Model | Provider | Fallback Sequence |
|---|---|---|---|
| AI Reasoning | GPT-OSS 120B (`openai/gpt-oss-120b`) | Groq | Qwen 3.8 27B → GPT-OSS 20B |
| Bangla STT | Whisper Large-v3 (`whisper-large-v3`) | Groq | Whisper Large-v3 Turbo → ElevenLabs → Web Speech API |
| English STT | Whisper Large-v3 (`whisper-large-v3`) | Groq | Whisper Large-v3 Turbo → ElevenLabs → Web Speech API |
| English TTS | Gemini 3.8 Flash-Lite TTS (`gemini-3.8-flash-lite-tts`) | Google | Microsoft Edge TTS |
| Bangla TTS | Gemini 3.8 Flash-Lite TTS (`gemini-3.8-flash-lite-tts`) | Google | Microsoft Edge TTS |

**Important:** Use the specified model identifiers as the initial configuration. All model identifiers must be configurable through environment variables rather than hardcoded in application code.

## 3. Implementation Requirements

### 3.1 AI Reasoning — Groq

Implement a sequential model fallback system using Groq.

**Model Priority:**

1. Primary: GPT-OSS 120B — `openai/gpt-oss-120b`
2. First fallback: Qwen 3.8 27B — `qwen/qwen3.8-27b`
3. Second fallback: GPT-OSS 20B — `openai/gpt-oss-20b`

**Implementation:**
- Use Groq's Chat Completions API.
- Attempt the primary model first.
- Automatically switch to the next available model when the current model encounters a rate limit, provider unavailability, timeout, or relevant service failure.
- Preserve existing system prompts, conversation history, reasoning workflows, and response structure across model switches.
- Support streaming responses where appropriate.
- Handle API errors, timeouts, rate limits, and malformed responses gracefully.
- If all three models fail, return a graceful, recoverable error.

**Important:** Do not assume that switching models will bypass organization-wide rate limits. Inspect the error response and rate-limit information before attempting fallback.

### 3.2 Speech-to-Text — Groq Whisper

Use Whisper Large-v3 as the primary transcription model for both Bangla and English.

**Model Priority:**

1. Primary: Whisper Large-v3 — `whisper-large-v3`
2. First fallback: Whisper Large-v3 Turbo — `whisper-large-v3-turbo`
3. Second fallback: ElevenLabs STT
4. Final fallback: Browser Web Speech API

**Implementation:**
- Record audio in the browser first.
- Submit the completed audio recording to the backend for transcription.
- Use the configured primary Whisper model.
- Explicitly specify the language when known:
  - Bangla: `bn`
  - English: `en`
- Preserve the original language in the transcription. Do not automatically translate Bangla into English.
- Return the recognized text to the frontend.
- Handle recording, uploading, transcription, and API errors appropriately.

**First Fallback — Whisper Large-v3 Turbo:**
- Activate automatically when the primary Whisper model fails or becomes unavailable.
- Use the same recorded audio.
- Preserve the selected transcription language.
- Handle rate limits, timeouts, and provider errors.

**Second Fallback — ElevenLabs STT:**
- Activate when both Groq Whisper models fail or become unavailable.
- Use ElevenLabs Speech-to-Text API.
- Support both Bangla and English transcription.
- Submit the same recorded audio.
- Preserve the original spoken language without unwanted translation.
- Handle API failures, timeouts, and unsupported language scenarios.

**Final Fallback — Web Speech API:**
- Activate only when Groq Whisper Large-v3, Whisper Large-v3 Turbo, and ElevenLabs all fail or become unavailable.
- Use the appropriate recognition language:
  - Bangla: `bn-BD`
  - English: `en-US`
- Check browser compatibility before activation.
- Clearly handle unsupported browsers and recognition failures.

**STT Fallback Priority:**

`Whisper Large-v3 → Whisper Large-v3 Turbo → ElevenLabs STT → Web Speech API`

### 3.3 Text-to-Speech — Google Gemini

Use Gemini 3.8 Flash-Lite TTS as the primary speech-generation model for both English and Bangla.

**Implementation:**
- Send the generated text to Gemini's TTS API.
- Generate natural, clear, conversational speech.
- Support both Bangla and English output.
- Select appropriate voice configurations for each language.
- Return playable audio to the frontend.
- Preserve the original text without unwanted translation or modification.
- Handle API failures, timeouts, and invalid audio responses.

**Fallback — Microsoft Edge TTS:**
- Use Microsoft Edge TTS through the `edge-tts` Python package.
- Activate it automatically when Gemini TTS fails or becomes unavailable.
- Select appropriate English or Bangla voices.
- Return audio in a browser-compatible format.
- Ensure the fallback works for both languages.

### 3.4 Provider and API Security

- All API keys must remain server-side.
- Never expose Groq, Google, or ElevenLabs API keys in frontend code.
- Store credentials in environment variables.
- Do not hardcode provider credentials or model identifiers throughout the application.
- Use centralized model configuration.
- Implement sensible request timeouts, error handling, and logging.
- Avoid unnecessary duplicate API requests.

### 3.5 Centralized Model Configuration

**Critical requirement: Model names and identifiers must NEVER be hardcoded in application code.**

All primary and fallback model identifiers must be defined through environment variables.

**Implementation requirements:**
- Define a separate environment variable for every configurable model.
- Read model identifiers from environment variables through a centralized configuration layer.
- All AI service integrations must reference this centralized configuration.
- Do not repeat model identifiers in controllers, services, API handlers, or other application logic.
- Changing or replacing a model must require updating only its corresponding environment variable, not modifying application code.
- Keep provider credentials and model configuration separate.
- Provide sensible configuration validation and clear errors when required variables are missing.
- Document all environment variables in the project's environment example file.
- Never expose secret environment variables to the frontend.
- No need of changing voice of TTS for AI Opponent Persona change. Always use a stable one.

This configuration must make future model upgrades and provider changes straightforward without requiring changes to the application's business logic.

## 4. Environment Configuration

Update the existing environment configuration with the following variables:

```env
# Provider API Keys
GROQ_API_KEY=
GEMINI_API_KEY=
ELEVENLABS_API_KEY=

# Groq - AI Reasoning
GROQ_REASONING_MODEL=openai/gpt-oss-120b
GROQ_REASONING_FALLBACK_MODEL_1=qwen/qwen3.8-27b
GROQ_REASONING_FALLBACK_MODEL_2=openai/gpt-oss-20b

# Groq - Speech-to-Text
GROQ_STT_MODEL=whisper-large-v3
GROQ_STT_FALLBACK_MODEL=whisper-large-v3-turbo

# ElevenLabs - Speech-to-Text
ELEVENLABS_STT_MODEL=

# Google - Text-to-Speech
GEMINI_TTS_MODEL=gemini-3.8-flash-lite-tts
```

**Configuration notes:**
- The ElevenLabs STT model identifier must be selected according to the supported API model options and configured through its environment variable.
- Configure Edge TTS through backend dependency installation rather than requiring another API key.
- Web Speech API does not require a model identifier or API key.
- Keep all environment variables containing secrets server-side.
- Include these variables in `.env.example` with appropriate non-secret defaults and empty credential placeholders.

## 5. Fallback and Error Handling

Implement the following provider-selection behavior:

| Service | Primary | First Fallback | Second Fallback | Final Fallback |
|---|---|---|---|---|
| AI Reasoning | GPT-OSS 120B | Qwen 3.8 27B | GPT-OSS 20B | None |
| STT | Whisper Large-v3 | Whisper Large-v3 Turbo | ElevenLabs | Web Speech API |
| TTS | Gemini TTS | Microsoft Edge TTS | None | None |

Additional requirements:

- Do not trigger fallback for every application error indiscriminately.
- Use fallback for provider unavailability, network failures, timeouts, rate limits, and relevant service errors.
- Distinguish model-specific rate limits from organization-wide restrictions whenever possible.
- Prevent infinite retries and duplicate requests.
- Notify the user when fallback services are being used.
- Log provider failures without exposing sensitive information.
- Preserve prompts, conversation context, selected language, and audio recordings during fallback.
- If all available services fail, display a recoverable error.
- Avoid unnecessary retries when a provider indicates that the request cannot succeed.

## 6. Testing and Acceptance Criteria

The update is complete when:

1. GPT-OSS 120B successfully handles Rostrum's existing AI reasoning workflows.
2. Qwen 3.8 27B activates when GPT-OSS 120B fails.
3. GPT-OSS 20B activates when both previous reasoning models fail.
4. Bangla and English recordings are accurately transcribed through Groq Whisper Large-v3.
5. Whisper Large-v3 Turbo activates when the primary STT model fails.
6. ElevenLabs activates when both Groq transcription models fail.
7. Web Speech API activates only when all three previous STT options fail.
8. All transcription methods preserve the original language.
9. Gemini generates playable English and Bangla speech.
10. Edge TTS successfully generates speech in both languages when Gemini fails.
11. Audio recording, uploading, playback, and error states work correctly.
12. API keys remain inaccessible to the browser.
13. All primary and fallback services are tested independently.
14. Model identifiers are loaded exclusively through centralized environment-based configuration.
15. Changing any model identifier through environment variables works without modifying application code.
16. Missing or invalid model configuration produces clear errors.
17. Existing Rostrum functionality and user experience remain unaffected.

## 7. Official Documentation & References

### Groq — AI Reasoning and STT
- Groq Console: https://console.groq.com/
- GPT-OSS 120B Model: https://console.groq.com/docs/model/openai/gpt-oss-120b
- Qwen 3.8 27B Model ID: `qwen/qwen3.8-27b`
- GPT-OSS 20B Model ID: `openai/gpt-oss-20b`
- Speech-to-Text Documentation: https://console.groq.com/docs/speech-to-text
- Whisper Large-v3 Model: https://console.groq.com/docs/model/whisper-large-v3
- Groq API Reference: https://console.groq.com/docs/api-reference
- OpenAI-Compatible API: https://console.groq.com/docs/openai
- Rate Limits: https://console.groq.com/docs/rate-limits

### Google — Gemini TTS
- Google AI Studio: https://aistudio.google.com/
- Gemini API Documentation: https://ai.google.dev/gemini-api/docs
- Gemini API Pricing and Model Availability: https://ai.google.dev/gemini-api/docs/pricing
- Gemini API Quickstart: https://ai.google.dev/gemini-api/docs/get-started
- Gemini TTS Model ID: `gemini-3.8-flash-lite-tts`

### ElevenLabs Documentation
- Official website: https://elevenlabs.io/
- API documentation: https://elevenlabs.io/docs
- Speech-to-Text API: https://elevenlabs.io/docs/api-reference/speech-to-text
- API Pricing: https://elevenlabs.io/pricing

### Microsoft — Edge TTS
- Edge TTS GitHub Repository: https://github.com/rany2/edge-tts
- Edge TTS Installation: `pip install edge-tts`
- Available Voices: https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support

### Browser — Web Speech API
- MDN Web Speech API: https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API
- Speech Recognition: https://developer.mozilla.org/en-US/docs/Web/API/SpeechRecognition

## 8. Scope Restrictions

- Do not redesign Rostrum's UI or UX. But update the UI text related to AI or model info.
- Do not introduce additional AI providers or models beyond the specified fallback sequence.
- Do not change existing AI reasoning workflows or application features.
- Do not replace the selected primary models.
- Do not hardcode model identifiers, provider credentials, or other configurable AI settings in application code.
- Do not implement unnecessary architectural changes.
- Do not duplicate model configuration across different application modules.
- Focus exclusively on model replacement, provider integration, centralized configuration, fallback mechanisms, security, and testing.

**Final Deliverable:** A fully integrated and tested Rostrum AI infrastructure using Groq for AI reasoning and primary transcription, sequential Groq model fallbacks, ElevenLabs and Web Speech API for additional STT fallbacks, Google Gemini for speech generation, and Microsoft Edge TTS as the TTS fallback. All model identifiers must be centrally configurable through environment variables, allowing future model updates without application code changes.
