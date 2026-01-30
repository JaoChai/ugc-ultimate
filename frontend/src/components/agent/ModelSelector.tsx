import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ExternalLink } from 'lucide-react';

interface ModelSelectorProps {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  description?: string;
  disabled?: boolean;
}

// Common OpenRouter models for quick reference
const POPULAR_MODELS = [
  { id: 'google/gemini-2.0-flash-exp', name: 'Gemini 2.0 Flash' },
  { id: 'anthropic/claude-3.5-sonnet', name: 'Claude 3.5 Sonnet' },
  { id: 'openai/gpt-4o', name: 'GPT-4o' },
  { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B' },
  { id: 'mistralai/mistral-large', name: 'Mistral Large' },
];

export function ModelSelector({
  value,
  onChange,
  label = 'Model',
  description,
  disabled,
}: ModelSelectorProps) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <Label htmlFor="model">{label}</Label>
        <a
          href="https://openrouter.ai/models"
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1"
        >
          Browse models
          <ExternalLink className="h-3 w-3" />
        </a>
      </div>

      <Input
        id="model"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="e.g. google/gemini-2.0-flash-exp"
        disabled={disabled}
      />

      {description && (
        <p className="text-xs text-muted-foreground">{description}</p>
      )}

      {/* Quick select popular models */}
      <div className="flex flex-wrap gap-1.5 pt-1">
        {POPULAR_MODELS.map((model) => (
          <button
            key={model.id}
            type="button"
            onClick={() => onChange(model.id)}
            disabled={disabled}
            className={`text-xs px-2 py-1 rounded-md transition-colors ${
              value === model.id
                ? 'bg-primary text-primary-foreground'
                : 'bg-muted hover:bg-muted/80 text-muted-foreground'
            } disabled:opacity-50 disabled:cursor-not-allowed`}
          >
            {model.name}
          </button>
        ))}
      </div>
    </div>
  );
}
