const { getConnection } = require('../db/connectionManager');
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const multer = require('multer');
const FormData = require('form-data');

const upload = multer({ dest: 'uploads/' });

// STREAMING AI RESPONSE (unchanged)
exports.callStreamingAI = async (req, res) => {
    const MODEL = req.body.model || 'deepseek-r1:7b';
    const PROMPT = req.body.prompt || 'Hi';

    try {
        const response = await axios.post(
            'http://localhost:11434/api/generate',
            { model: MODEL, prompt: PROMPT, stream: true },
            { headers: { 'Content-Type': 'application/json' }, responseType: 'stream' }
        );

        res.setHeader('Content-Type', 'text/plain; charset=utf-8');
        res.setHeader('Transfer-Encoding', 'chunked');

        response.data.on('data', chunk => {
            const text = chunk.toString().trim();
            if (!text) return;

            try {
                const payload = JSON.parse(text);
                const content = payload.response;
                if (content) res.write(content);
            } catch (err) {
                console.error('Chunk parse error:', err);
            }
        });

        response.data.on('end', () => res.end());
        response.data.on('error', err => {
            console.error('Stream error:', err);
            res.status(500).end('Error during streaming.');
        });

    } catch (err) {
        console.error('Ollama error:', err.response?.status, err.response?.data || err.message);
        res.status(err.response?.status || 500).send(err.response?.data || err.message);
    }
};

// SQL GENERATION + EXECUTION + CUSTOM SUMMARY
exports.callSQLAI = async (req, res) => {
    const promptInput = (req.body.prompt || "").trim();
    if (!promptInput) return res.status(400).json({ error: "No prompt provided" });

    try {
        const dbKey = req.dbName || 'mysql';
        const conn = await getConnection(dbKey);

        // Step 1: Get schema for all tables
        const [tables] = await conn.query(`SHOW TABLES`);
        const tableKey = Object.keys(tables[0])[0];
        const tableNames = tables.map(t => t[tableKey]);

        const schemaList = [];
        for (const tableName of tableNames) {
            const [columns] = await conn.query(`SHOW COLUMNS FROM \`${tableName}\``);
            const columnDetails = columns.map(c => `${c.Field} (${c.Type})`).join(', ');
            schemaList.push(`- ${tableName}: ${columnDetails}`);
        }

        const schemaOverview = schemaList.join('\n');

        // Step 2: Put schema in system prompt
        const generationPrompt = `You are DB assistant. You need to run SQL queries aligned with the user's intent.

Here are the tables and their columns:
${schemaOverview}

Rules:
- Only use the tables and columns listed above.
- Do not invent any tables or fields.
- Add schema prefix if necessary.
- Output must be in valid SQL for MySQL.
`;

        // Step 3: AI tool call
        const tools = [
            { type: "function", function: { name: "run_sql_query", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } },
            { type: "function", function: { name: "update_query", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } },
            { type: "function", function: { name: "describe_table", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } },
            { type: "function", function: { name: "list_all_tables", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } },
            { type: "function", function: { name: "count_rows", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } },
            { type: "function", function: { name: "sum_column", parameters: { type: "object", properties: { query: { type: "string" } }, required: ["query"] } } }
        ];

        const aiResponse = await axios.post("http://localhost:11434/api/chat", {
            model: "MFDoom/deepseek-r1-tool-calling:7b",
            messages: [
                { role: "system", content: generationPrompt },
                { role: "user", content: promptInput }
            ],
            tools,
            tool_choice: "auto",
            stream: false
        });

        const toolCallStr = aiResponse?.data?.message?.content || "";
        const queryMatch = toolCallStr.match(/"query"\s*:\s*"([^"]+)"/);
        const toolMatch = toolCallStr.match(/"name"\s*:\s*"([^"]+)"/);
        const sqlToRun = queryMatch ? queryMatch[1] : "";
        const toolName = toolMatch ? toolMatch[1] : "unknown";

        if (!sqlToRun) return res.status(400).json({ error: "No valid tool call", raw: aiResponse.data });

        const [rows] = await conn.query(sqlToRun);

        // Smart summary override
        let summary = '';
        const keys = rows?.length ? Object.keys(rows[0]) : [];

        if ((promptInput.includes("list") || promptInput.includes("display")) && rows.length && keys.length === 1) {
            const field = keys[0];
            const values = rows.map(row => row[field]).filter(v => v !== null && v !== undefined).slice(0, 1000);
            summary = `${field} list: ${values.join(', ')}`;
        } else {
            const summaryResponse = await axios.post("http://localhost:11434/api/chat", {
                model: "MFDoom/deepseek-r1-tool-calling:7b",
                messages: [
                    {
                        role: "system",
                        content: `You are a SQL assistant. Your job is to give a clear and accurate final answer, in the format the user requested.
- If the user asked for a list, show the actual values.
- If count, show exact total.
- No explanation or steps.
Examples:
- "10 users found."
- "Total sales: RM 20,500."
- "vehicle_id list: 23, 42, 57, 81"`
                    },
                    { role: "user", content: promptInput },
                    { role: "assistant", content: toolCallStr },
                    { role: "tool", content: JSON.stringify(rows) }
                ],
                stream: false
            });

            summary = summaryResponse?.data?.message?.content?.trim().replace(/<\/?[^>]+>/gi, '') || "";
            summary = summary.replace(/^.*?(?=\bThere\b|\bTotal\b|\bUpdated\b|\bThe\b)/i, '').trim();
        }

        res.json({ tool: toolName, sql: sqlToRun, result: rows, summary });

    } catch (err) {
        console.error("SQL/AI error:", err.message);
        res.status(500).json({ error: "AI or SQL error", details: err.message });
    }
};


// 🗣️ Whisper Transcription
exports.transcribeWithWhisper = [
    upload.single('audio'),
    async (req, res) => {
        if (!req.file) return res.status(400).json({ error: 'No audio uploaded' });

        const filePath = path.resolve(req.file.path);
        const form = new FormData();
        form.append('audio', fs.createReadStream(filePath));

        try {
            const response = await axios.post('http://192.168.2.70:5005/transcribe', form, {
                headers: form.getHeaders()
            });

            fs.unlinkSync(filePath); // cleanup
            const transcript = response?.data?.text || '';
            res.json({ transcript });

        } catch (err) {
            console.error('Whisper API error:', err.message);
            res.status(500).json({ error: 'Whisper transcription failed', details: err.message });
        }
    }
];
