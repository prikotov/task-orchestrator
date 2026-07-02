[![Read in Russian](https://img.shields.io/badge/Lang-Русский-blue)](README.md)
[![Read in English](https://img.shields.io/badge/Lang-English-blue)](README.en.md)

# TasK-orchestrator

### CLI 代理編排器

在單一冗長的工作階段中與 AI 代理進行長時間、多面向的互動會使其上下文超載。代理同時需要在記憶中保持流程指令、每個步驟的細節以及您無意間的口誤。工作階段越長，雜訊越多，結果也越差。上下文會逐漸退化，回答品質也隨之下降。

另一方面，您同時處理多個平行任務：在代理之間切換、手動啟動每個後續步驟、遺失上下文——忘記幾分鐘前請求的內容——並花時間恢復它。認知負荷增加，您感到疲憊，工作品質也隨之下降。

此外，不同模型各有優勢：Gemini 擅長撰寫文本，GPT 模型更精確地遵循指令並處理程式碼，Opus 能以深度分析解決複雜問題，來自中國的新模型正積極追趕。模型只是整個組合的一部分：它在一個 harness（管理外殼，如 Codex、Claude Code 或 pi）中運作，搭配特定的工具集和自己的上下文處理方式。Codex 與 OpenAI 模型搭配良好，Claude Code 與 Anthropic 搭配，而 OpenCode、Kilocode 和 pi 支援多個供應商。您希望為每個工作環節選擇合適的組合——CLI 代理、模型、角色和技能——但手動設定、切換和在工作視窗之間複製上下文是令人疲倦的。

TasK-orchestrator 透過模擬真實團隊的運作來解決這些問題，其中每位參與者都有自己的角色、技能和上下文，任務以指派的方式傳遞——畢竟人們沒有共用的大腦。為此提供了四個工具：

**角色（Roles）** — 描述代理個性的 Markdown 檔案：行為特徵（DISC、Big Five）、專業知識、工作風格、優勢與弱點。角色會綁定其所掌握的技能。模型將此檔案作為系統指令接收，並在該角色的框架內行動。

**YAML 配置** — 適用於步驟順序已事先確定的流程。您描述角色、為每個角色綁定 CLI 代理、模型和啟動參數，安排步驟和分支條件。編排器按順序遍歷鏈條，不偏離路線。

**技能（Skills）** — 角色所掌握的能力。技能以 Markdown 檔案描述，包含逐步說明和輔助腳本。角色自行選擇合適的技能並根據情況應用。

**子代理（Subagents）** — 將角色作為獨立代理在乾淨上下文中啟動的方式。代理以文字和檔案的形式接收角色、任務指派和其他上下文。沒有聊天歷史和先前工作階段的產物。

編排有兩種運作模式。第一種是 YAML 配置定義流程，由編排器執行，依序啟動代理完成每個步驟。第二種是代理驅動流程：團隊負責人將技能作為指令接收，自行決定啟動哪個子代理、何時要求返工、何時上報問題。

---

## 角色（Roles）

角色是模型作為系統指令接收的 Markdown 檔案。Front matter 透過多個行為模型（DISC、Big Five、Adizes、Belbin、Jungian 原型）描述個性、專業知識和綁定的技能。檔案主體展開 front matter 中定義的個性：角色描述、個人特質、工作風格、行為原則和規則。

同一專業領域可以有多個具有不同行為特徵的角色。兩個架構師面對相同指派但不同特徵，會給出不同的解決方案——一個嚴格遵循標準，另一個尋找盲點和替代方案。兩個後端開發者：一個按所有規則編寫整潔程式碼，另一個重視速度和簡潔。這種觀點的對抗是有意識的選擇，因為它如同在真實團隊中一樣帶來效益。它在需要利益衝突的地方尤其重要：在程式碼審查中，審查者從另一個角度看待解決方案——他的優先考量是簡潔和執行速度，而非程式碼的工整；在腦力激盪中，不同特徵的參與者比單一代理產生更多想法，因為單一代理傾向於同意自己和他人。我們研究過的經典編排系統不處理行為特徵：它們不關注角色的「個性」。

使用者可以建立新角色作為 Markdown 檔案——具有獨特的個性、專業知識和技能集。角色描述建議請參閱 [ROLE-CREATION.md](docs/agents/roles/ROLE-CREATION.md)。

角色範例：[`docs/agents/roles/team/`](docs/agents/roles/team/)。

---

## YAML 配置

YAML 檔案定義確定性流程：您描述角色、為每個角色綁定 AI 代理、模型和啟動參數，安排步驟和分支條件。編排器是一個 PHP 引擎，逐步遍歷鏈條。行為由配置決定：哪些步驟、以什麼順序、帶什麼條件。

支援兩種鏈條類型。`static` — 固定的步驟序列，順序事先已知：分析師解析需求 → 後端開發者實作 → 審查者檢查。`dynamic` — 主持人自行決定誰在何時行動：管理流程、指派執行者、要求返工。

鏈條支援：失敗時的延遲重試、斷路器（在重複錯誤時暫時封鎖）、備用執行者（fallback）、透過 shell 命令執行的品質閘門（quality gates）、限額約束、透過 `when:` 的條件分支、每個步驟的 JSONL 日誌記錄。

範例——腦力激盪，四個角色討論架構決策：

```yaml
# config/chains.yaml
roles:
  team_lead_alex:
    prompt_file: docs/agents/roles/team/team_lead_alex.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

  system_architect_gandalf:
    prompt_file: docs/agents/roles/team/system_architect_gandalf.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  system_architect_loki:
    prompt_file: docs/agents/roles/team/system_architect_loki.ru.md
    command: [codex, exec, --dangerously-bypass-approvals-and-sandbox, --json, --model, gpt-5.5]

  backend_developer_tony:
    prompt_file: docs/agents/roles/team/backend_developer_tony.ru.md
    command: [pi, --mode, json, -p, --no-session, --provider, zai, --model, glm-5-turbo, --system-prompt, "@system-prompt"]

chains:
  brainstorm:
    type: dynamic
    description: "有引導者的腦力激盪，帶有建設性衝突"
    timeout: 600
    facilitator: team_lead_alex
    participants: [system_architect_gandalf, system_architect_loki, backend_developer_tony]
    max_rounds: 20
    max_time: 3600
    prompts:
      brainstorm_system: prompts/brainstorm/brainstorm_system.txt
      facilitator_append: prompts/brainstorm/facilitator_append.txt
      facilitator_start: prompts/brainstorm/facilitator_start.txt
      facilitator_continue: prompts/brainstorm/facilitator_continue.txt
      facilitator_finalize: prompts/brainstorm/facilitator_finalize.txt
      participant_append: prompts/brainstorm/participant_append.txt
      participant_user: prompts/brainstorm/participant_user.txt
```

```bash
php vendor/bin/task-orchestrator agent:orchestrate \
  "哪些模組應從 AgentRunner 中分離？" \
  --chain=brainstorm
```

詳細資訊：[鏈條文件](docs/guide/chains.md)、[可靠性](docs/guide/reliability.md)。配置範例：[`config/chains.yaml`](config/chains.yaml)。

---

## 技能（Skills）

技能是角色所掌握的能力。由 Markdown 檔案（`SKILL.md`）描述，包含逐步說明和輔助腳本。角色根據情況自行選擇並載入合適的技能。

這是透過技能實現編排的方式：角色接收指令並自行決定如何行動——啟動哪個子代理、何時要求返工、何時上報問題。例如，團隊負責人透過 [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md) 從規劃到 merge 管理史詩任務（epic），透過 [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md) 管理單獨的任務。此方法比 YAML 鏈條更靈活，但可靠性較低：代理可能忘記步驟、偏離指令或選擇次優路徑。因此，對於可以透過 YAML 描述的流程，最好使用 YAML 配置。而技能——用於透過代理進行的編排，或作為 YAML 鏈條的封裝：例如，[`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) 技能描述如何進行腦力激盪，而激盪本身實作為 YAML 鏈條。

技能在 front matter 中綁定到角色。角色只接收其工作所需的技能。

可用技能：

| 技能 | 功能 |
|---|---|
| [`become-role`](docs/agents/skills/become-role/SKILL.md) | 進入角色：將角色的 skills 揭露至代理上下文（Agent Skills 格式） |
| [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md) | 啟動從屬代理：角色 + 任務指派 + 上下文。超時控制、停滞偵測、輸出過濾 |
| [`task-via-subagents`](docs/agents/skills/task-via-subagents/SKILL.md) | 從規劃到 merge 執行任務：實作 → self-review → code review → 返工 → PR |
| [`epic-via-subagents`](docs/agents/skills/epic-via-subagents/SKILL.md) | 透過子代理執行包含多個任務的史詩任務（epic） |
| [`brainstorm`](docs/agents/skills/brainstorm/SKILL.md) | 腦力激盪：引導者主持討論，參與者辯論，產出含決策的紀錄 |
| [`retrospective`](docs/agents/skills/retrospective/SKILL.md) | 史詩任務（epic）後的回顧：分析流程品質，提出改進建議 |
| [`agent-report`](docs/agents/skills/agent-report/SKILL.md) | 將代理報告儲存至檔案以便追溯 |

使用者可以建立新技能，作為包含 `SKILL.md` 和腳本的目錄——比照現有技能的方式。建議：[SKILL-CREATION.md](docs/agents/skills/SKILL-CREATION.md)。範例：[`docs/agents/skills/`](docs/agents/skills/)。

---

## 子代理（Subagents）

子代理是將角色作為獨立代理在乾淨上下文中啟動的方式。代理以文字和檔案的形式接收角色、任務指派和上下文。沒有聊天歷史——每次啟動都是全新開始，就像您將任務指派給團隊中的另一個成員一樣。

這解決了上下文退化的問題：與其在一個冗長的工作階段中讓代理積累雜訊，任務被分解為指派。每個子代理完成自己的部分並回傳結果。團隊負責人或其他指定角色協調結果，不會用先前步驟的細節超載執行者的上下文，也不會用多餘的實作細節超載自己的上下文。

透過 [`run-subagent`](docs/agents/skills/run-subagent/SKILL.md) 技能啟動。

---

## 與其他方案的比較

AI 角色團隊研究了 25+ 個 AI 代理編排框架——LangGraph、CrewAI、Archon 等。完整比較：[框架研究](docs/research/agent-frameworks-summary.md) 和 [coding 代理比較](docs/research/coding-agents-summary.md)。

TasK-orchestrator 相對於已研究方案的優勢：

- **流程控制**：透過 YAML 的確定性步驟鏈條、失敗重試、斷路器、限額約束
- **角色行為特徵**：同一專業領域內具有不同個性的對抗性角色
- **乾淨上下文中的委派**：無聊天歷史繼承的子代理，透過指定角色進行協調
- **技能作為指令**：由代理自主執行的文字流程，或 YAML 鏈條的封裝
- **品質檢查**：直接在步驟鏈條中執行的 linter 和測試

發展路線圖——請參閱 [ROADMAP](docs/releases/ROADMAP-2026-Q2-Q3.md)。

---

## 快速入門

TasK-orchestrator 是一個 CLI 工具。最低需求：PHP >= 8.4 和一個 CLI 代理（例如 [pi CLI](https://github.com/prikotov/pi) 或 [Codex CLI](https://github.com/openi/codex)）。

安裝：

```bash
composer require prikotov/task-orchestrator
```

安裝後執行一次 `agent:init` — 它會在 `<專案>/.agents/skills/` 建立共用 skill `become-role` 的符號連結，讓您的 AI 代理（pi/codex）將其視為原生 skill（透過跨客戶端 `.agents/skills/` 慣例）：

```bash
php vendor/bin/task-orchestrator agent:init
```

最小的 `config/chains.yaml` — 兩個角色和一個兩步驟鏈條：

```yaml
roles:
  analyst:
    prompt_file: prompts/analyst.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

  developer:
    prompt_file: prompts/developer.md
    command: [pi, --mode, json, -p, --no-session, --model, gpt-4o, --system-prompt, "@system-prompt"]

chains:
  implement:
    steps:
      - { type: agent, role: analyst, name: analyze }
      - { type: agent, role: developer, name: implement }
      - { type: quality_gate, command: "vendor/bin/phpunit", label: "Tests", timeout_seconds: 120 }
```

從專案根目錄執行：

```bash
php vendor/bin/task-orchestrator agent:orchestrate "Add user registration endpoint"
```

不啟動鏈條也能檢查已配置角色的連通性：

```bash
php vendor/bin/task-orchestrator validate:connectivity --dry-run
php vendor/bin/task-orchestrator validate:connectivity --role=system_analyst_sherlock --timeout=30
```

如何設定執行者、選擇 YAML 選項並連接到專案——請參閱[文件](docs/guide/chains.md)。

---

## 文件

| 文件 | 說明 |
|---|---|
| [架構](docs/guide/architecture.md) | DDD 分層、模組、CQRS |
| [CLI 命令](docs/guide/cli.md) | 編排、單一代理執行、角色連通性檢查 |
| [鏈條](docs/guide/chains.md) | Static / Dynamic / Conditional、YAML DSL |
| [角色](docs/guide/roles.md) | 角色配置、prompt files、runners |
| [可靠性](docs/guide/reliability.md) | Retry、Circuit Breaker、Fallback、Sessions/Resume |
| [Observability](docs/guide/observability.md) | Audit trail、hooks、metrics |
| [Hooks](docs/guide/hooks.md) | Post-step hooks |
| [擴展](docs/guide/extension.md) | 新增 runners 和 strategies |
| [慣例](docs/conventions/index.md) | DDD 模式、分層、程式碼風格 |
| [25+ 框架研究](docs/research/agent-frameworks-summary.md) | LangGraph、CrewAI、Archon 及其他 22 個框架 |

---

## License

[MIT](LICENSE) · Copyright © 2025–2026 prikotov
