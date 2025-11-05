import { motion } from 'framer-motion';
import {
    ChevronRight,
    Upload,
    Shield,
    AlertTriangle,
    AlertCircle,
    XCircle,
    CheckCircle2,
    Lightbulb,
    ArrowRight,
    Share2,
    Info,
    Mail,
    MessageSquare,
    Github,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import ScoreDisplay from './ScoreDisplay';
import ScoreBreakdown from './ScoreBreakdown';
import { useEffect } from 'react';
import confetti from 'canvas-confetti';

interface Analysis {
    filename?: string;
    overall_score?: number;
    confidence?: 'high' | 'medium' | 'low';
    parseability_score?: number;
    format_score?: number;
    keyword_score?: number;
    contact_score?: number;
    content_score?: number;
    critical_issues?: string[];
    warnings?: string[];
    suggestions?: string[];
    ai_unavailable?: boolean;
    ai_error_message?: string | null;
}

interface ResumeResultsProps {
    analysis: Analysis;
    onReset: () => void;
}

export default function ResumeResults({ analysis, onReset }: ResumeResultsProps) {
    const score = analysis.overall_score || 0;

    // Celebrate if score is excellent (70+)
    useEffect(() => {
        if (score >= 70) {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 },
            });
        }
    }, [score]);

    const getConfidenceBadge = () => {
        const confidence = analysis.confidence || 'medium';
        const config = {
            high: {
                bg: 'bg-emerald-100',
                text: 'text-emerald-700',
                icon: Shield,
                label: 'High Confidence',
            },
            medium: {
                bg: 'bg-amber-100',
                text: 'text-amber-700',
                icon: AlertTriangle,
                label: 'Medium Confidence',
            },
            low: {
                bg: 'bg-rose-100',
                text: 'text-rose-700',
                icon: AlertCircle,
                label: 'Low Confidence',
            },
        };

        const { bg, text, icon: Icon, label } = config[confidence];

        return (
            <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${bg} ${text} mb-4`}>
                <Icon className="w-3.5 h-3.5" />
                <span>{label}</span>
            </div>
        );
    };

    return (
        <div className="max-w-[1120px] mx-auto px-4 py-12 bg-slate-50">
            {/* Header Section */}
            <motion.div
                initial={{ opacity: 0, y: -20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6 }}
                className="mb-12"
            >
                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-slate-500 mb-4">
                    <Link href="/resume-checker" className="hover:text-slate-900 transition-colors">
                        Home
                    </Link>
                    <ChevronRight className="w-4 h-4" />
                    <span>Analysis Results</span>
                </div>

                {/* Heading Row */}
                <div className="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 mb-1">Resume Analysis Results</h1>
                        {analysis.filename && (
                            <p className="text-sm text-slate-500">File: {analysis.filename}</p>
                        )}
                    </div>
                    <button
                        onClick={onReset}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 hover:-translate-y-0.5 transition-all duration-200 shadow-[0_4px_6px_rgba(79,70,229,0.3)]"
                    >
                        <Upload className="w-4 h-4" />
                        Analyze Another Resume
                    </button>
                </div>
            </motion.div>

            {/* Score Hero Card */}
            <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.6, delay: 0.2 }}
                className="mb-12 bg-white p-12 rounded-[24px] shadow-[0_10px_40px_-10px_rgba(0,0,0,0.1)] relative overflow-hidden"
            >
                {/* Background Decoration */}
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.1),transparent_50%)] pointer-events-none" />

                <div className="relative z-10">
                    {/* Top Row */}
                    <div className="flex justify-between items-start mb-8">
                        <div>
                            {getConfidenceBadge()}
                            <p className="text-base font-medium text-slate-600 mb-3">
                                Your ATS Compatibility Score
                            </p>
                        </div>
                        <button className="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-100 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-200 transition-colors">
                            <Share2 className="w-4 h-4" />
                            Share
                        </button>
                    </div>

                    {/* Score Display */}
                    <ScoreDisplay score={score} />

                    {/* Disclaimer */}
                    <p className="mt-8 text-xs text-slate-500 text-center leading-relaxed">
                        Scores calibrated against ResumeWorded/JobScan. 65+ is good ATS compatibility.
                    </p>
                </div>
            </motion.div>

            {/* Score Breakdown Section */}
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.4 }}
                className="mb-12"
            >
                <h2 className="text-2xl font-bold text-slate-900 mb-6">Score Breakdown</h2>
                <ScoreBreakdown
                    parseabilityScore={analysis.parseability_score}
                    formatScore={analysis.format_score}
                    keywordScore={analysis.keyword_score}
                    contactScore={analysis.contact_score}
                    contentScore={analysis.content_score}
                />
            </motion.div>

            {/* Issues & Suggestions Section */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                {/* Critical Fixes */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.6 }}
                    className="bg-white p-8 rounded-2xl border border-slate-200 min-h-[400px]"
                >
                    <div className="flex items-center gap-3 mb-6">
                        <AlertCircle className="w-6 h-6 text-rose-500" />
                        <h3 className="text-lg font-semibold text-slate-900">Critical Fixes Required</h3>
                        {analysis.critical_issues && analysis.critical_issues.length > 0 && (
                            <span className="w-6 h-6 rounded-full bg-rose-500 text-white text-xs font-medium flex items-center justify-center">
                                {analysis.critical_issues.length}
                            </span>
                        )}
                    </div>
                    {analysis.critical_issues && analysis.critical_issues.length > 0 ? (
                        <div className="space-y-4">
                            {analysis.critical_issues.map((issue, index) => (
                                <div
                                    key={index}
                                    className="flex gap-3 items-start p-3 bg-rose-50 rounded-lg border-l-4 border-rose-500"
                                >
                                    <XCircle className="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5" />
                                    <p className="text-sm leading-relaxed text-slate-700">{issue}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-20">
                            <CheckCircle2 className="w-12 h-12 text-emerald-500 mb-4" />
                            <p className="text-sm text-slate-500 text-center">No issues found in this category</p>
                        </div>
                    )}
                </motion.div>

                {/* Warnings */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.7 }}
                    className="bg-white p-8 rounded-2xl border border-slate-200 min-h-[400px]"
                >
                    <div className="flex items-center gap-3 mb-6">
                        <AlertTriangle className="w-6 h-6 text-amber-500" />
                        <h3 className="text-lg font-semibold text-slate-900">Warnings</h3>
                        {analysis.warnings && analysis.warnings.length > 0 && (
                            <span className="w-6 h-6 rounded-full bg-amber-500 text-white text-xs font-medium flex items-center justify-center">
                                {analysis.warnings.length}
                            </span>
                        )}
                    </div>
                    {analysis.warnings && analysis.warnings.length > 0 ? (
                        <div className="space-y-4">
                            {analysis.warnings.map((warning, index) => (
                                <div
                                    key={index}
                                    className="flex gap-3 items-start p-3 bg-amber-50 rounded-lg border-l-4 border-amber-500"
                                >
                                    <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                                    <p className="text-sm leading-relaxed text-slate-700">{warning}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-20">
                            <CheckCircle2 className="w-12 h-12 text-emerald-500 mb-4" />
                            <p className="text-sm text-slate-500 text-center">No issues found in this category</p>
                        </div>
                    )}
                </motion.div>

                {/* Recommended Improvements */}
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.8 }}
                    className="bg-white p-8 rounded-2xl border border-slate-200 min-h-[400px]"
                >
                    <div className="flex items-center gap-3 mb-6">
                        <Lightbulb className="w-6 h-6 text-indigo-600" />
                        <h3 className="text-lg font-semibold text-slate-900">Recommended Improvements</h3>
                        {analysis.suggestions && analysis.suggestions.length > 0 && (
                            <span className="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-medium flex items-center justify-center">
                                {analysis.suggestions.length}
                            </span>
                        )}
                    </div>
                    {analysis.suggestions && analysis.suggestions.length > 0 ? (
                        <div className="space-y-4">
                            {analysis.suggestions.map((suggestion, index) => (
                                <div
                                    key={index}
                                    className="flex gap-3 items-start p-3 bg-indigo-50 rounded-lg border-l-4 border-indigo-600"
                                >
                                    <ArrowRight className="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" />
                                    <p className="text-sm leading-relaxed text-slate-700">{suggestion}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-20">
                            <CheckCircle2 className="w-12 h-12 text-emerald-500 mb-4" />
                            <p className="text-sm text-slate-500 text-center">No issues found in this category</p>
                        </div>
                    )}
                </motion.div>
            </div>

            {/* Disclaimer Footer */}
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.8 }}
                className="mb-16 bg-slate-100 p-8 rounded-2xl border-l-4 border-indigo-600"
            >
                <div className="flex items-center gap-3 mb-4">
                    <Info className="w-6 h-6 text-indigo-600" />
                    <h3 className="text-base font-semibold text-slate-900">About This Analysis</h3>
                </div>
                <p className="text-sm leading-relaxed text-slate-600 mb-4">
                    This analysis is based on documented ATS best practices from industry research including
                    TopResume, Jobscan, and Harvard Career Services. While no tool can guarantee compatibility
                    with all ATS systems, following these recommendations significantly improves your chances of
                    passing automated screening. Different companies use different ATS systems with varying
                    requirements.
                </p>
                {analysis.ai_unavailable && (
                    <div className="inline-flex items-center gap-2 px-4 py-2 bg-amber-100 rounded-lg text-xs font-medium text-amber-800 mb-4">
                        <Info className="w-4 h-4" />
                        <span>BETA: This tool is continuously improving. Results may vary ±10 points from other ATS checkers.</span>
                    </div>
                )}
                <div className="flex gap-6 flex-wrap">
                    <a
                        href="mailto:hello@northcodelab.com"
                        className="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline transition-colors"
                    >
                        <Mail className="w-4 h-4" />
                        Report an Issue
                    </a>
                    <button className="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline transition-colors">
                        <MessageSquare className="w-4 h-4" />
                        Give Feedback
                    </button>
                    <a
                        href="https://github.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:underline transition-colors"
                    >
                        <Github className="w-4 h-4" />
                        View on GitHub
                    </a>
                </div>
            </motion.div>

            {/* CTA Section */}
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 1 }}
                className="bg-gradient-to-br from-indigo-600 to-purple-600 p-12 rounded-[24px] text-center relative overflow-hidden"
            >
                <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle at 50% 50%, rgba(255,255,255,0.1) 1px, transparent 0)', backgroundSize: '40px 40px' }} />
                <div className="relative z-10">
                    <h2 className="text-3xl font-bold text-white mb-3">Want to Improve Your Score?</h2>
                    <p className="text-base text-white/90 mb-8 max-w-[600px] mx-auto">
                        Upload an updated version of your resume and track your progress over time
                    </p>
                    <button
                        onClick={onReset}
                        className="inline-flex items-center gap-2 px-8 py-4 bg-white text-indigo-600 rounded-xl text-base font-semibold hover:scale-105 transition-all duration-300 shadow-[0_10px_30px_rgba(0,0,0,0.2)]"
                    >
                        <Upload className="w-5 h-5" />
                        Analyze Another Resume
                    </button>
                </div>
            </motion.div>
        </div>
    );
}

