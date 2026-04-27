# analysis_v2 解析格式规范

> 来源：`questions_Final.json` 实测  
> 适用：Qz03–Qz15 批量解析生成，及后续人工校对

---

## 一、字段定义表

| 字段 | 类型 | 必填 | 字数要求 | 说明 |
|------|------|:----:|----------|------|
| `core_point` | string | ✅ | **5–50字** | 核心考点名称，题目考查的知识点 |
| `answer_reason` | string | ✅ | **≥30字，建议90–120字** | 正确答案的详细依据，解释为什么选这个 |
| `theory_explanation` | string | 条件 | **50–100字** 或空串 | 涉及原理/原则/理论时填，否则填 `""` |
| `memory_tip` | string | ✅ | **≤30字** | 记忆口诀，朗朗上口，帮助记住答案 |
| `quick_memo` | string | 条件 | **≤20字** 或空串 | 操作/步骤题的速记短句，否则填 `""` |
| `source_needed` | boolean | ✅ | — | `true` 若题目涉及法规/文件 |
| `source_hint` | string | 条件 | — | 法规/文件名，如 `"《安全生产法》"` |
| `option_analysis` | array | ✅ | 每条reason **20–50字** | 每个选项的正误判断+理由 |
| `highlights.numbers` | array | ✅ | — | 题目中的关键数字（如"30日"）|
| `highlights.entities` | array | ✅ | — | 主要实体：机构、人物、概念名称 |
| `highlights.laws` | array | ✅ | — | 相关法规名称 |
| `highlights.categories` | array | ✅ | — | 知识分类标签（1–3个） |
| `negation_words` | array | 自动 | — | 脚本客户端正则提取，**不经过API** |
| `limit_words` | array | 自动 | — | 同上 |

### 字数统计参考（来自 Final.json 实测）

| 字段 | 建议范围 | 实测样本均值 |
|------|----------|------------|
| `core_point` | 10–40字 | ~25字 |
| `answer_reason` | 60–150字 | ~99字 |
| `theory_explanation`（非空） | 50–220字 | ~170字 |
| `memory_tip` | 10–28字 | ~22字 |
| `option_analysis[].reason` | 15–50字 | ~30字 |

---

## 二、题型差异说明

| 题型 | 选项数 | answer格式 | 特殊说明 |
|------|-------|-----------|---------|
| **单选题** | 4项（A–D） | 单个字母 `"C"` | option_analysis 必须覆盖全部4项 |
| **多选题** | 4–5项（A–E） | 连续字母 `"ACE"` | option_analysis 必须覆盖全部选项 |
| **判断题** | 2项（A=正确, B=错误） | `"A"` 或 `"B"` | option_analysis 只需 A 和 B 两项 |

---

## 三、完整样板

### 样板一：单选题（ID=1）

```json
{
  "id": 1,
  "number": 1,
  "type": "单选题",
  "question": "社会主义中国的安全生产管理，离不开中国共产党的领导和（     ）的引导。",
  "options": ["规章制度", "自治条例", "国家法规政策", "地方性法规"],
  "answer": "C",
  "category": "法律法规",
  "analysis_v2": {
    "id": 1,
    "core_point": "安全生产管理的领导与引导机制",
    "answer_reason": "社会主义中国安全生产管理坚持党的领导和国家法规政策的引导，这是我国安全生产管理体制的基本框架，国家法规政策是宏观层面的制度保障",
    "highlights": {
      "numbers": [],
      "entities": ["中国共产党", "国家法规政策"],
      "laws": ["安全生产法"],
      "categories": ["安全生产管理体制"]
    },
    "option_analysis": [
      {"option": "A", "text": "规章制度", "correct": false, "reason": "规章制度属于具体操作层面，非宏观引导层面"},
      {"option": "B", "text": "自治条例", "correct": false, "reason": "自治条例适用范围有限，仅适用于民族自治地方"},
      {"option": "C", "text": "国家法规政策", "correct": true, "reason": "与党的领导并列，国家法规政策是安全生产管理的宏观引导力量"},
      {"option": "D", "text": "地方性法规", "correct": false, "reason": "地方性法规层级较低，不具备全国性引导作用"}
    ],
    "memory_tip": "党领国引，法规政策是引导",
    "source_needed": true,
    "source_hint": "安全生产法",
    "theory_explanation": "我国安全生产管理体制强调党的领导和国家法规政策的引导相结合，体现了政治引领与法治保障的统一，这是社会主义安全生产管理的根本特征。",
    "quick_memo": "",
    "negation_words": [],
    "limit_words": []
  }
}
```

**字段字数**：core_point=17字 / answer_reason=62字 / theory_explanation=57字 / memory_tip=11字

---

### 样板二：多选题（ID=401）

```json
{
  "id": 401,
  "number": 401,
  "type": "多选题",
  "question": "《安全生产管理知识》指导用书把施工过程中的安全生产管理知识分别设置为（     ）。",
  "options": ["管理", "实操", "法规", "法律", "政策"],
  "answer": "ACE",
  "category": "施工技术",
  "analysis_v2": {
    "id": 401,
    "core_point": "安全生产管理知识体系构成",
    "answer_reason": "《安全生产管理知识》指导用书将施工过程中安全生产管理知识分为管理、法规、政策三个主要方面，涵盖了管理体系、法律依据和政策导向。B项"实操"和D项"法律"均不在该用书的知识分类体系中。",
    "highlights": {
      "numbers": [],
      "entities": ["安全生产管理知识", "管理", "法规", "政策"],
      "laws": ["《安全生产管理知识》指导用书"],
      "categories": ["安全管理"]
    },
    "option_analysis": [
      {"option": "A", "text": "管理", "correct": true, "reason": "属于安全生产管理知识的重要组成部分"},
      {"option": "B", "text": "实操", "correct": false, "reason": "实操不属于该指导用书的知识分类体系"},
      {"option": "C", "text": "法规", "correct": true, "reason": "法规是安全生产管理的重要依据和组成部分"},
      {"option": "D", "text": "法律", "correct": false, "reason": "法律概念过于宽泛，指导用书采用法规表述"},
      {"option": "E", "text": "政策", "correct": true, "reason": "政策导向是安全生产管理知识的重要内容"}
    ],
    "memory_tip": "管理法规政策三部分",
    "source_needed": true,
    "source_hint": "《安全生产管理知识》指导用书",
    "theory_explanation": "",
    "quick_memo": "",
    "negation_words": [],
    "limit_words": []
  }
}
```

**注意**：多选题 option_analysis 必须覆盖全部 **5 项**（A–E），错误项也要写 reason 说明为何不选。

---

### 样板三：判断题（ID=600）

```json
{
  "id": 600,
  "number": 600,
  "type": "判断题",
  "question": "安全生产管理一般包括四个部分，再往外延伸还会涉及安全生产管理的甲方服务等。",
  "options": ["正确", "错误"],
  "answer": "B",
  "category": "判断题",
  "analysis_v2": {
    "id": 600,
    "core_point": "安全生产管理的组成部分",
    "answer_reason": "安全生产管理一般包括四个部分，但再往外延伸涉及的是安全生产管理服务，而非"甲方服务"这一特定表述，题干表述不准确，故选错误。",
    "highlights": {
      "numbers": ["四个部分"],
      "entities": ["安全生产管理", "甲方服务"],
      "laws": [],
      "categories": ["安全管理"]
    },
    "option_analysis": [
      {"option": "A", "text": "正确", "correct": false, "reason": "延伸部分应为安全生产管理服务，表述为"甲方服务"不准确"},
      {"option": "B", "text": "错误", "correct": true, "reason": "题干中"甲方服务"的说法错误，应为安全生产管理服务"}
    ],
    "memory_tip": "四部分延伸是服务，非甲方",
    "source_needed": false,
    "source_hint": "",
    "theory_explanation": "",
    "quick_memo": "",
    "negation_words": [],
    "limit_words": []
  }
}
```

**注意**：判断题 option_analysis **只有 A（正确）和 B（错误）两项**。

---

## 四、质量检查规则

脚本 `analyze_qz.py` 中 `validate_analysis()` 执行以下检查，生成解析时需满足全部规则：

| # | 规则 | 失败示例 |
|---|------|---------|
| 1 | `core_point` 非空，**5–50字** | `""` 或超过50字 |
| 2 | `answer_reason` 非空，**≥30字** | 少于30字的简单重复 |
| 3 | `option_analysis` 覆盖**全部选项** | 4选项题只返回了3个 |
| 4 | `memory_tip` 非空，**≤30字** | 超过30字的长句 |
| 5 | `highlights` 含全部4个子键 | 缺少 `categories` 键 |

质量检查失败时，脚本自动重试最多 3 次，仍失败则标记为 `failed` 并记录日志。

---

## 五、Prompt 系统指令（verbatim）

```
你是安全生产考试解析专家。我会发给你若干道题，请逐题生成解析，
输出纯JSON数组，不要任何markdown。每题格式：
{"id":<整数>,"core_point":"考点","answer_reason":"答案依据",
"highlights":{"numbers":[],"entities":[],"laws":[],"categories":[]},
"option_analysis":[{"option":"A","text":"","correct":true,"reason":""}],
"memory_tip":"口诀20字内","source_needed":true,"source_hint":"法规名",
"theory_explanation":"若涉及原理/原则/理论则50-100字解释否则空串",
"quick_memo":"若为实操/操作/步骤题则20字速记否则空串"}
```

---

## 六、使用模型配置（2026-04-22 实测）

| Worker | 模型 | 平均响应 | 说明 |
|--------|------|---------|------|
| W0 | `gpt-5.4-mini` | 2.1s | 最快，首选 |
| W1 | `qwen3-coder-plus` | 2.9s | 稳定，中文强 |
| W2 | `MiniMax-M2.7-highspeed` | 3.2s | 长上下文好 |

批次大小 3 题/批，3 个 worker 并发，预计全量 1,668 题约 **20–30 分钟**。
