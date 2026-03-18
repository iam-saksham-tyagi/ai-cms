# AI-Powered Visual CMS

This is an advanced, AI-driven website builder that combines the pixel-perfect control of drag-and-drop with the generative power of Large Language Models. Build, style, and deploy modern Tailwind CSS landing pages instantly.

<img width="1366" height="728" alt="image" src="https://github.com/user-attachments/assets/0f57d0f5-ee40-42e4-822e-a3a257ef705d" />


## The Vision
Most AI website builders strip away your control, giving you a black-box result. This platform takes a different approach: **Surgical AI Generation**. 

Powered by a custom integration of GrapesJS and modern LLM APIs, users can select specific DOM elements on the canvas and use natural language to instantly rewrite copy, update Tailwind classes, or generate entirely new UI components—without ever leaving the visual editor.

## Key Features
* **AI Component Generation:** Select any node and prompt the AI to restructure, style, or populate it with content.
* **Intelligent Safety Net:** Custom JavaScript DOM-diffing prevents the AI from catastrophically deleting your layout if it hallucinates.
* **Drag & Drop Canvas:** Built on top of a highly customized GrapesJS instance.
* **Tailwind Native:** Generates and parses utility classes flawlessly.
* **Instant Preview:** Live, isolated preview environment for generated sites.

## Tech Stack
* **Backend:** Laravel (PHP)
* **Frontend Canvas:** GrapesJS, HTML5, Alpine.js
* **Styling:** Tailwind CSS
* **AI Integration:** Groq / Moonshot APIs (Structured prompt-to-HTML generation)

---

## 📸 Interface Preview

### The Dashboard
<img width="1366" height="728" alt="image" src="https://github.com/user-attachments/assets/d6b8f0d9-ff27-4b76-8d89-95adfed05b73" />

*Manage multiple sites, view live statuses, and access the editor.*

### The AI Editor
<img width="1363" height="726" alt="image" src="https://github.com/user-attachments/assets/760a6278-6bbd-4940-bc35-633ea430c6b3" />

*Select an element, type a prompt, and watch the UI morph instantly.*

---

## 💻 Running Locally

If you want to spin this up on your local machine or a GitHub Codespace:

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/yourusername/your-repo-name.git](https://github.com/yourusername/your-repo-name.git)
   cd your-repo-name
   
2. **Install PHP and Node dependencies:**
    ```bash
    composer install
    npm install && npm run build
   
3. **Environment Setup:**
    ```bash
    cp .env.example .env
    php artisan key:generate
    Make sure to add your AI API keys (Groq/Moonshot) and database credentials to the .env file.

4. **Run Migrations:**
    ```bash
    php artisan migrate

5. **Start the server:**
    ```bash
    php artisan serve
