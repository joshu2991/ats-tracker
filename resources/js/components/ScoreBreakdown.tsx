import ProgressBar from './ProgressBar';

interface ScoreBreakdownProps {
    parseabilityScore?: number;
    formatScore?: number;
    keywordScore?: number;
    contactScore?: number;
    contentScore?: number;
}

export default function ScoreBreakdown({
    parseabilityScore,
    formatScore,
    keywordScore,
    contactScore,
    contentScore,
}: ScoreBreakdownProps) {
    const getScoreColor = (score: number, max: number): 'green' | 'yellow' | 'red' => {
        const safeScore = Math.max(0, score || 0);
        const safeMax = Math.max(1, max);
        const percentage = (safeScore / safeMax) * 100;
        if (percentage >= 80) {
            return 'green';
        }
        if (percentage >= 60) {
            return 'yellow';
        }
        return 'red';
    };

    const getScoreLabel = (category: string): string => {
        const labels: Record<string, string> = {
            parseability: 'Parseability (Can ATS read it?)',
            format: 'Format (Standard structure?)',
            keyword: 'Keywords (Relevant skills?)',
            contact: 'Contact Info (Easy to find?)',
            content: 'Content Quality (Achievements, verbs?)',
        };
        return labels[category] || category;
    };

    const getScoreDescription = (category: string): string => {
        const descriptions: Record<string, string> = {
            parseability: 'Measures if the resume can be properly parsed by ATS systems. Low scores indicate scanned images, tables, or layout issues.',
            format: 'Evaluates if the resume follows standard ATS-friendly structure with proper section headers and formatting.',
            keyword: 'Assesses the presence and relevance of technical keywords that match job requirements.',
            contact: 'Checks if contact information is easily accessible and properly formatted for ATS systems.',
            content: 'Reviews the quality of content including action verbs, quantifiable achievements, and appropriate length.',
        };
        return descriptions[category] || '';
    };

    return (
        <div className="space-y-6">
            {parseabilityScore !== undefined && (
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {getScoreLabel('parseability')}
                        </h3>
                        <div className="group relative">
                            <svg
                                className="h-4 w-4 cursor-help text-gray-400 dark:text-gray-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            <div className="absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                {getScoreDescription('parseability')}
                            </div>
                        </div>
                    </div>
                    <ProgressBar
                        value={parseabilityScore}
                        max={100}
                        color={getScoreColor(parseabilityScore, 100)}
                    />
                </div>
            )}

            {formatScore !== undefined && (
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {getScoreLabel('format')}
                        </h3>
                        <div className="group relative">
                            <svg
                                className="h-4 w-4 cursor-help text-gray-400 dark:text-gray-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            <div className="absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                {getScoreDescription('format')}
                            </div>
                        </div>
                    </div>
                    <ProgressBar
                        value={formatScore}
                        max={100}
                        color={getScoreColor(formatScore, 100)}
                    />
                </div>
            )}

            {keywordScore !== undefined && (
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {getScoreLabel('keyword')}
                        </h3>
                        <div className="group relative">
                            <svg
                                className="h-4 w-4 cursor-help text-gray-400 dark:text-gray-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            <div className="absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                {getScoreDescription('keyword')}
                            </div>
                        </div>
                    </div>
                    <ProgressBar
                        value={keywordScore}
                        max={100}
                        color={getScoreColor(keywordScore, 100)}
                    />
                </div>
            )}

            {contactScore !== undefined && (
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {getScoreLabel('contact')}
                        </h3>
                        <div className="group relative">
                            <svg
                                className="h-4 w-4 cursor-help text-gray-400 dark:text-gray-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            <div className="absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                {getScoreDescription('contact')}
                            </div>
                        </div>
                    </div>
                    <ProgressBar
                        value={contactScore}
                        max={100}
                        color={getScoreColor(contactScore, 100)}
                    />
                </div>
            )}

            {contentScore !== undefined && (
                <div>
                    <div className="mb-2 flex items-center gap-2">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            {getScoreLabel('content')}
                        </h3>
                        <div className="group relative">
                            <svg
                                className="h-4 w-4 cursor-help text-gray-400 dark:text-gray-500"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                    clipRule="evenodd"
                                />
                            </svg>
                            <div className="absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                {getScoreDescription('content')}
                            </div>
                        </div>
                    </div>
                    <ProgressBar
                        value={contentScore}
                        max={100}
                        color={getScoreColor(contentScore, 100)}
                    />
                </div>
            )}
        </div>
    );
}
