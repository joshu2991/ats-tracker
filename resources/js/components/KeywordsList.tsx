interface KeywordsListProps {
    keywords: Record<string, number>;
    uniqueCount: number;
}

export default function KeywordsList({ keywords, uniqueCount }: KeywordsListProps) {
    // Ensure keywords is an object
    const safeKeywords = keywords || {};
    
    // High-demand keywords (can be customized)
    const highDemandKeywords = [
        'React',
        'AWS',
        'Docker',
        'Kubernetes',
        'Python',
        'JavaScript',
        'TypeScript',
        'Laravel',
        'Node.js',
        'Git',
    ];

    // Suggested keywords that are missing
    const foundKeywordNames = Object.keys(safeKeywords);
    const suggestedKeywords = highDemandKeywords.filter(
        (keyword) => !foundKeywordNames.some((found) => found.toLowerCase() === keyword.toLowerCase())
    );

    // Sort keywords by count (descending)
    const sortedKeywords = Object.entries(safeKeywords).sort(([, a], [, b]) => (b as number) - (a as number));

    return (
        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">
                Keywords Found ({uniqueCount})
            </h3>

            {sortedKeywords.length > 0 ? (
                <div className="mb-6">
                    <div className="flex flex-wrap gap-2">
                        {sortedKeywords.map(([keyword, count]) => {
                            const isHighDemand = highDemandKeywords.some(
                                (hd) => hd.toLowerCase() === keyword.toLowerCase()
                            );

                            return (
                                <span
                                    key={keyword}
                                    className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium ${
                                        isHighDemand
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    }`}
                                >
                                    {keyword}
                                    {count > 1 && (
                                        <span className="rounded-full bg-gray-200 px-1.5 py-0.5 text-xs dark:bg-gray-600">
                                            {count}
                                        </span>
                                    )}
                                </span>
                            );
                        })}
                    </div>
                </div>
            ) : (
                <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    No technical keywords detected. Consider adding more technical skills.
                </p>
            )}

            {suggestedKeywords.length > 0 && (
                <div>
                    <h4 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Suggested Keywords to Add:
                    </h4>
                    <div className="flex flex-wrap gap-2">
                        {suggestedKeywords.slice(0, 10).map((keyword) => (
                            <span
                                key={keyword}
                                className="inline-flex rounded-full border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                {keyword}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

