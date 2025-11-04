interface SuggestionsPanelProps {
    suggestions: string[];
}

export default function SuggestionsPanel({ suggestions }: SuggestionsPanelProps) {
    if (suggestions.length === 0) {
        return (
            <div className="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                <p className="text-sm font-medium text-green-800 dark:text-green-200">
                    Great job! Your resume looks good.
                </p>
            </div>
        );
    }

    const getIcon = (suggestion: string): string => {
        if (suggestion.toLowerCase().includes('email')) {
            return '✉️';
        }
        if (suggestion.toLowerCase().includes('skill') || suggestion.toLowerCase().includes('technical')) {
            return '💻';
        }
        if (suggestion.toLowerCase().includes('linkedin')) {
            return '💼';
        }
        if (suggestion.toLowerCase().includes('word') || suggestion.toLowerCase().includes('page')) {
            return '📄';
        }
        if (suggestion.toLowerCase().includes('bullet')) {
            return '•';
        }
        if (suggestion.toLowerCase().includes('github')) {
            return '🐙';
        }
        return '💡';
    };

    return (
        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Actionable Suggestions</h3>
            <ul className="space-y-3">
                {suggestions.map((suggestion, index) => (
                    <li key={index} className="flex items-start gap-3">
                        <span className="text-xl">{getIcon(suggestion)}</span>
                        <span className="flex-1 text-gray-700 dark:text-gray-300">{suggestion}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

