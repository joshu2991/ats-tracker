import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ArrowLeft,
    FileText,
    Clock,
    Upload,
    FileSearch,
    Layout,
    Hash,
    Mail,
    AlertCircle,
    AlertTriangle,
    Lightbulb,
    Shield,
    Info,
    CheckCircle2,
    TrendingUp,
    TrendingDown,
    Target,
    Zap,
    Award,
    AlertOctagon,
    Sparkles,
    BarChart3,
    ChevronDown,
} from 'lucide-react';
import { useCountUp } from '../hooks/useCountUp';
import CircularProgress from './CircularProgress';
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

interface ResumeResultsDashboardProps {
    analysis: Analysis;
    onReset: () => void;
}

export default function ResumeResultsDashboard({ analysis, onReset }: ResumeResultsDashboardProps) {
    const score = analysis.overall_score || 0;
    const displayScore = useCountUp(score, 2000);
    
    // Accordion state - only one category expanded at a time
    const [expandedCategory, setExpandedCategory] = useState<string | null>(
        analysis.critical_issues && analysis.critical_issues.length > 0 ? 'critical' : 
        analysis.warnings && analysis.warnings.length > 0 ? 'warnings' : 
        analysis.suggestions && analysis.suggestions.length > 0 ? 'improvements' : null
    );

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

    // Toggle accordion - only one open at a time
    const toggleCategory = (categoryId: string) => {
        setExpandedCategory(expandedCategory === categoryId ? null : categoryId);
    };

    // Get relative time
    const getRelativeTime = () => {
        return '2 minutes ago'; // In real app, calculate from timestamp
    };

    // Get score gradient
    const getScoreGradient = () => {
        if (score >= 70) {
            return 'from-emerald-500 via-emerald-600 to-emerald-700';
        }
        if (score >= 50) {
            return 'from-amber-500 via-amber-600 to-amber-700';
        }
        return 'from-rose-500 via-rose-600 to-rose-700';
    };

    // Get score rating
    const getScoreRating = () => {
        if (score >= 85) {
            return { label: 'Excellent', sublabel: 'Ready to Apply', icon: Award };
        }
        if (score >= 70) {
            return { label: 'Very Good', sublabel: 'Minor Tweaks Needed', icon: CheckCircle2 };
        }
        if (score >= 50) {
            return { label: 'Good', sublabel: 'Needs Improvement', icon: AlertTriangle };
        }
        if (score >= 30) {
            return { label: 'Fair', sublabel: 'Major Issues Found', icon: AlertCircle };
        }
        return { label: 'Poor', sublabel: 'Needs Rewrite', icon: AlertOctagon };
    };

    // Get category status
    const getCategoryStatus = (categoryScore: number) => {
        if (categoryScore >= 90) {
            return { label: 'Excellent', color: 'emerald', icon: Award };
        }
        if (categoryScore >= 70) {
            return { label: 'Good', color: 'emerald', icon: CheckCircle2 };
        }
        if (categoryScore >= 50) {
            return { label: 'Fair', color: 'amber', icon: AlertTriangle };
        }
        if (categoryScore >= 30) {
            return { label: 'Poor', color: 'rose', icon: AlertCircle };
        }
        return { label: 'Critical', color: 'rose', icon: AlertOctagon };
    };

    // Get score explanation from actual score
    const getScoreExplanation = (categoryScore: number) => {
        if (categoryScore >= 90) {
            return 'Excellent performance - well above industry standards';
        }
        if (categoryScore >= 70) {
            return 'Good performance - meets industry standards';
        }
        if (categoryScore >= 50) {
            return 'Fair performance - room for improvement';
        }
        if (categoryScore >= 30) {
            return 'Poor performance - needs significant attention';
        }
        return 'Critical issues detected - immediate action required';
    };

    // Get score color
    const getScoreColor = (categoryScore: number) => {
        if (categoryScore >= 70) {
            return {
                icon: 'text-emerald-500',
                bg: 'bg-emerald-50',
                text: 'text-emerald-700',
                border: 'border-emerald-200',
                gradient: 'from-emerald-500 to-emerald-600',
                badge: 'bg-emerald-500',
                ring: 'ring-emerald-500/20',
            };
        }
        if (categoryScore >= 50) {
            return {
                icon: 'text-amber-500',
                bg: 'bg-amber-50',
                text: 'text-amber-700',
                border: 'border-amber-200',
                gradient: 'from-amber-500 to-amber-600',
                badge: 'bg-amber-500',
                ring: 'ring-amber-500/20',
            };
        }
        return {
            icon: 'text-rose-500',
            bg: 'bg-rose-50',
            text: 'text-rose-700',
            border: 'border-rose-200',
            gradient: 'from-rose-500 to-rose-600',
            badge: 'bg-rose-500',
            ring: 'ring-rose-500/20',
        };
    };

    // Get confidence badge
    const getConfidenceBadge = () => {
        const confidence = analysis.confidence || 'medium';
        const config = {
            high: { icon: Shield, label: 'High Confidence', color: 'emerald' as const },
            medium: { icon: Info, label: 'Medium Confidence', color: 'amber' as const },
            low: { icon: AlertTriangle, label: 'Low Confidence', color: 'rose' as const },
        };
        const { icon: Icon, label, color } = config[confidence];
        const colorClasses: Record<'emerald' | 'amber' | 'rose', string> = {
            emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
            amber: 'bg-amber-100 text-amber-700 border-amber-200',
            rose: 'bg-rose-100 text-rose-700 border-rose-200',
        };
        return (
            <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium border ${colorClasses[color]}`}>
                <Icon className="w-3.5 h-3.5" />
                <span>{label}</span>
            </div>
        );
    };

    // Extract insights from issue text intelligently
    const extractInsightFromIssue = (issueText: string, categoryId: string) => {
        const lowerText = issueText.toLowerCase();
        
        // Extract actionable fix from issue text
        let fix = '';
        if (lowerText.includes('table')) {
            fix = 'Remove tables and convert to plain text format';
        } else if (lowerText.includes('column') || lowerText.includes('multi-column')) {
            fix = 'Use single-column layout instead of multi-column';
        } else if (lowerText.includes('email') && !lowerText.includes('found')) {
            fix = 'Add email address to the header section';
        } else if (lowerText.includes('phone') && !lowerText.includes('found')) {
            fix = 'Add phone number to the header section';
        } else if (lowerText.includes('linkedin')) {
            fix = 'Add LinkedIn profile URL to contact section';
        } else if (lowerText.includes('keyword') || lowerText.includes('technical')) {
            fix = 'Add more relevant technical keywords';
        } else if (lowerText.includes('summary') || lowerText.includes('objective')) {
            fix = 'Add a professional summary section';
        } else if (lowerText.includes('metric') || lowerText.includes('quantifiable')) {
            fix = 'Add quantifiable achievements with numbers';
        } else if (lowerText.includes('action verb')) {
            fix = 'Use more action verbs (Led, Built, Optimized, etc.)';
        } else if (lowerText.includes('scanned') || lowerText.includes('image')) {
            fix = 'Convert scanned PDF to text-based format';
        } else {
            // Use the issue text itself as the fix if we can't extract better
            fix = issueText;
        }

        // Estimate impact based on category and issue type
        let impact = '';
        if (categoryId === 'critical') {
            impact = 'May cause automatic rejection by ATS systems';
        } else if (categoryId === 'warnings') {
            impact = 'Reduces ATS compatibility by 10-20%';
        } else {
            impact = 'Could improve score by 5-10 points';
        }

        // Estimate difficulty based on issue type
        let difficulty: 'Easy' | 'Medium' | 'Hard' = 'Medium';
        let timeEstimate = '15 min';
        
        if (lowerText.includes('add') && (lowerText.includes('email') || lowerText.includes('phone') || lowerText.includes('linkedin'))) {
            difficulty = 'Easy';
            timeEstimate = '5 min';
        } else if (lowerText.includes('table') || lowerText.includes('column')) {
            difficulty = 'Hard';
            timeEstimate = '30 min';
        } else if (lowerText.includes('keyword') || lowerText.includes('summary')) {
            difficulty = 'Medium';
            timeEstimate = '15 min';
        }

        return { fix, impact, difficulty, timeEstimate };
    };

    // Calculate improvement potential
    const calculateImprovementPotential = () => {
        const criticalCount = analysis.critical_issues?.length || 0;
        const warningCount = analysis.warnings?.length || 0;
        
        // Estimate potential improvement
        let potentialPoints = 0;
        if (criticalCount > 0) {
            potentialPoints += criticalCount * 15; // Each critical fix = ~15 points
        }
        if (warningCount > 0) {
            potentialPoints += warningCount * 5; // Each warning fix = ~5 points
        }
        
        return Math.min(100 - score, potentialPoints);
    };

    // Get top priority issues (critical first, then warnings)
    const getTopPriorities = () => {
        const priorities: Array<{ text: string; type: 'critical' | 'warnings' | 'improvements'; priority: number }> = [];
        
        // Critical issues first (highest priority)
        (analysis.critical_issues || []).forEach((issue) => {
            priorities.push({ text: issue, type: 'critical', priority: 1 });
        });
        
        // Warnings second
        (analysis.warnings || []).slice(0, 3).forEach((warning) => {
            priorities.push({ text: warning, type: 'warnings', priority: 2 });
        });
        
        // Improvements last (but only if no critical/warnings)
        if (priorities.length === 0) {
            (analysis.suggestions || []).slice(0, 2).forEach((suggestion) => {
                priorities.push({ text: suggestion, type: 'improvements', priority: 3 });
            });
        }
        
        return priorities.slice(0, 3); // Top 3
    };

    // Menu items
    const menuItems = [
        {
            id: 'parseability',
            label: 'Parseability',
            icon: FileSearch,
            score: analysis.parseability_score,
            description: 'PDF text extraction and document readability',
        },
        {
            id: 'format',
            label: 'Format & Structure',
            icon: Layout,
            score: analysis.format_score,
            description: 'Section headers, document structure, and formatting',
        },
        {
            id: 'keyword',
            label: 'Keywords & Skills',
            icon: Hash,
            score: analysis.keyword_score,
            description: 'Technical skills and industry keyword density',
        },
        {
            id: 'contact',
            label: 'Contact Info',
            icon: Mail,
            score: analysis.contact_score,
            description: 'Email, phone, LinkedIn, and location visibility',
        },
        {
            id: 'content',
            label: 'Content Quality',
            icon: FileText,
            score: analysis.content_score,
            description: 'Action verbs, metrics, and impact statements',
        },
    ];

    const issueItems = [
        {
            id: 'critical',
            label: 'Critical Fixes',
            icon: AlertCircle,
            count: analysis.critical_issues?.length || 0,
            color: 'rose',
            items: analysis.critical_issues || [],
        },
        {
            id: 'warnings',
            label: 'Warnings',
            icon: AlertTriangle,
            count: analysis.warnings?.length || 0,
            color: 'amber',
            items: analysis.warnings || [],
        },
        {
            id: 'improvements',
            label: 'Improvements',
            icon: Lightbulb,
            count: analysis.suggestions?.length || 0,
            color: 'indigo',
            items: analysis.suggestions || [],
        },
    ];

    const rating = getScoreRating();
    const RatingIcon = rating.icon;

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-50">
            {/* Header Bar */}
            <div className="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 shadow-sm">
                <div className="w-full px-4 sm:px-6 lg:px-8 py-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <button
                                onClick={onReset}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium text-slate-700 transition-colors"
                            >
                                <ArrowLeft className="w-4 h-4" />
                                <span className="hidden sm:inline">Back to Upload</span>
                                <span className="sm:hidden">Back</span>
                            </button>
                            <div className="hidden sm:flex items-center gap-3 text-sm text-slate-600">
                                <FileText className="w-4 h-4 text-indigo-600" />
                                <span className="font-medium truncate max-w-[200px]">
                                    {analysis.filename || 'Resume.pdf'}
                                </span>
                            </div>
                        </div>
                        <button
                            onClick={onReset}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors shadow-md hover:shadow-lg"
                        >
                            <Upload className="w-4 h-4" />
                            <span className="hidden sm:inline">Analyze Another</span>
                            <span className="sm:hidden">New</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* Main Content - Two Column Layout (33% / 67%) */}
            <div className="w-full px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-12">
                    {/* Left Column: Hero Score + Quick Wins (33% width) */}
                    <div className="lg:col-span-1 space-y-8">
                        {/* Hero Score Section */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                        >
                            <div className={`relative overflow-hidden rounded-3xl bg-gradient-to-br ${getScoreGradient()} shadow-2xl p-8 lg:p-10`}>
                                {/* Background Pattern */}
                                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.15),transparent)] pointer-events-none" />
                                <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.1)_0%,transparent_50%)] pointer-events-none" />
                                
                                <div className="relative z-10">
                                    <div className="flex flex-col items-center text-center">
                                        {/* Circular Progress Ring */}
                                        <div className="mb-6">
                                            <div className="relative w-40 h-40 sm:w-48 sm:h-48 lg:w-56 lg:h-56 mx-auto">
                                                {/* Mobile: 160px */}
                                                <div className="sm:hidden">
                                                    <CircularProgress 
                                                        score={score} 
                                                        size={160} 
                                                        strokeWidth={12} 
                                                    />
                                                </div>
                                                {/* Tablet: 192px */}
                                                <div className="hidden sm:block lg:hidden">
                                                    <CircularProgress 
                                                        score={score} 
                                                        size={192} 
                                                        strokeWidth={14} 
                                                    />
                                                </div>
                                                {/* Desktop: 224px */}
                                                <div className="hidden lg:block">
                                                    <CircularProgress 
                                                        score={score} 
                                                        size={224} 
                                                        strokeWidth={16} 
                                                    />
                                                </div>
                                                <div className="absolute inset-0 flex flex-col items-center justify-center">
                                                    <span className="text-5xl sm:text-6xl lg:text-7xl font-bold text-white leading-none">{displayScore}</span>
                                                    <span className="text-xl sm:text-2xl lg:text-3xl text-white/80 relative -top-2">/100</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Score Details */}
                                        <div className="mb-4">
                                            <p className="text-sm font-medium text-white/90 uppercase tracking-wider mb-3">
                                                Overall ATS Score
                                            </p>
                                            <div className="flex items-center justify-center gap-3 mb-3">
                                                <div>
                                                    <h1 className="text-2xl lg:text-3xl font-bold text-white mb-1">{rating.label}</h1>
                                                    <p className="text-base text-white/90">{rating.sublabel}</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex flex-wrap items-center justify-center gap-3 mb-6">
                                            {getConfidenceBadge()}
                                            <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-md rounded-full text-sm font-medium text-white border border-white/30">
                                                <Sparkles className="w-4 h-4" />
                                                <span>+{calculateImprovementPotential()} pts</span>
                                            </div>
                                        </div>

                                        {/* Quick Stats Grid */}
                                        <div className="grid grid-cols-3 gap-3 w-full max-w-sm">
                                            <div className="bg-white/10 backdrop-blur-md rounded-xl p-3 border border-white/20">
                                                <div className="text-xl font-bold text-white mb-1">
                                                    {analysis.critical_issues?.length || 0}
                                                </div>
                                                <div className="text-xs text-white/80 uppercase tracking-wider">Critical</div>
                                            </div>
                                            <div className="bg-white/10 backdrop-blur-md rounded-xl p-3 border border-white/20">
                                                <div className="text-xl font-bold text-white mb-1">
                                                    {analysis.warnings?.length || 0}
                                                </div>
                                                <div className="text-xs text-white/80 uppercase tracking-wider">Warnings</div>
                                            </div>
                                            <div className="bg-white/10 backdrop-blur-md rounded-xl p-3 border border-white/20">
                                                <div className="text-xl font-bold text-white mb-1">
                                                    {(analysis.critical_issues?.length || 0) + (analysis.warnings?.length || 0) + (analysis.suggestions?.length || 0)}
                                                </div>
                                                <div className="text-xs text-white/80 uppercase tracking-wider">Total</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </motion.div>

                        {/* Quick Wins Section - Column Layout */}
                        {getTopPriorities().length > 0 && (
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                            >
                                <div className="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-2xl p-6 lg:p-8 border border-indigo-200 shadow-lg">
                                    <div className="flex items-center gap-3 mb-6">
                                        <div className="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                                            <Zap className="w-6 h-6 text-white" />
                                        </div>
                                        <div>
                                            <h2 className="text-xl lg:text-2xl font-bold text-slate-900">Quick Wins</h2>
                                            <p className="text-sm text-slate-600">Top priority fixes to improve your score</p>
                                        </div>
                                    </div>
                                    <div className="space-y-4">
                                        {getTopPriorities().map((priority, index) => {
                                            const insight = extractInsightFromIssue(priority.text, priority.type);
                                            const priorityColors = {
                                                critical: { bg: 'bg-rose-100', border: 'border-rose-500', text: 'text-rose-700', icon: AlertOctagon },
                                                warnings: { bg: 'bg-amber-100', border: 'border-amber-500', text: 'text-amber-700', icon: AlertTriangle },
                                                improvements: { bg: 'bg-indigo-100', border: 'border-indigo-500', text: 'text-indigo-700', icon: Lightbulb },
                                            };
                                            const colors = priorityColors[priority.type] || priorityColors.improvements;
                                            const PriorityIcon = colors.icon;
                                            
                                            return (
                                                <motion.div
                                                    key={index}
                                                    initial={{ opacity: 0, scale: 0.95 }}
                                                    animate={{ opacity: 1, scale: 1 }}
                                                    transition={{ duration: 0.4, delay: 0.3 + index * 0.1 }}
                                                    className={`p-5 bg-white rounded-xl border-l-4 ${colors.border} shadow-md hover:shadow-lg transition-all duration-300`}
                                                >
                                                    <div className="flex items-start gap-3 mb-3">
                                                        <PriorityIcon className={`w-5 h-5 ${colors.text} flex-shrink-0 mt-0.5`} />
                                                        <p className="text-sm font-semibold text-slate-900 leading-relaxed flex-1">
                                                            {insight.fix}
                                                        </p>
                                                    </div>
                                                    <div className="ml-8 flex items-center gap-3 text-xs">
                                                        <span className="flex items-center gap-1.5 text-slate-600">
                                                            <Clock className="w-3 h-3" />
                                                            {insight.timeEstimate}
                                                        </span>
                                                        <span className={`px-2 py-1 rounded-md text-xs font-medium ${
                                                            insight.difficulty === 'Easy' ? 'bg-emerald-100 text-emerald-700' :
                                                            insight.difficulty === 'Medium' ? 'bg-amber-100 text-amber-700' :
                                                            'bg-rose-100 text-rose-700'
                                                        }`}>
                                                            {insight.difficulty}
                                                        </span>
                                                    </div>
                                                </motion.div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </motion.div>
                        )}

                        {/* Footer Note */}
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.6, delay: 0.8 }}
                            className="pt-6"
                        >
                            <div className="inline-flex items-start gap-2 px-4 py-3 bg-slate-100 rounded-lg text-xs text-slate-600 leading-relaxed">
                                <Info className="w-4 h-4 flex-shrink-0 mt-0.5" />
                                <span>This analysis is based on documented ATS best practices from industry research including
                                    TopResume, Jobscan, ResumeWorded, and Harvard Career Services. While no tool can guarantee compatibility
                                    with all ATS systems, following these recommendations significantly improves your chances of
                                    passing automated screening. Different companies use different ATS systems with varying
                                    requirements.</span>
                            </div>
                        </motion.div>
                    </div>

                    {/* Right Column: Detailed Breakdown + Issues (67% width) */}
                    <div className="lg:col-span-2 space-y-8">
                        {/* Detailed Breakdown Section */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.4 }}
                        >
                            <div className="flex items-center gap-3 mb-6">
                                <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                                    <BarChart3 className="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900">Detailed Breakdown</h2>
                                    <p className="text-sm text-slate-600 mt-1">Category-by-category analysis</p>
                                </div>
                            </div>

                            {/* 3 Columns x 2 Rows Grid */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {menuItems.map((item, index) => {
                                    const Icon = item.icon;
                                    const itemScore = item.score || 0;
                                    const colors = getScoreColor(itemScore);
                                    const displayScoreValue = useCountUp(itemScore, 1500);
                                    const status = getCategoryStatus(itemScore);
                                    const StatusIcon = status.icon;

                                    return (
                                        <motion.div
                                            key={item.id}
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.5, delay: 0.5 + index * 0.1 }}
                                            className={`bg-white rounded-2xl p-6 border-2 ${colors.border} shadow-lg hover:shadow-xl transition-all duration-300 ${colors.ring} ring-2`}
                                        >
                                            {/* Header */}
                                            <div className="flex items-start justify-between mb-4">
                                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                                    <div className={`w-12 h-12 rounded-xl ${colors.bg} flex items-center justify-center shadow-md flex-shrink-0`}>
                                                        <Icon className={`w-6 h-6 ${colors.icon}`} />
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <h3 className="text-base font-bold text-slate-900 mb-1">{item.label}</h3>
                                                        <p className="text-xs text-slate-600 leading-relaxed">{item.description}</p>
                                                    </div>
                                                </div>
                                                <div className="text-right ml-4 flex-shrink-0">
                                                    <div className={`text-2xl font-bold ${colors.text}`}>{displayScoreValue}</div>
                                                    <div className="text-sm text-slate-400">/100</div>
                                                </div>
                                            </div>

                                            {/* Circular Progress Ring */}
                                            <div className="flex items-center justify-center mb-4">
                                                <div className="relative w-32 h-32">
                                                    <CircularProgress score={itemScore} size={128} strokeWidth={10} />
                                                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                                                        <span className={`text-3xl font-bold ${colors.text}`}>{displayScoreValue}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Status Badge & Explanation */}
                                            <div className="text-center">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold ${colors.bg} ${colors.text} mb-3`}>
                                                    <StatusIcon className="w-4 h-4" />
                                                    <span>{status.label}</span>
                                                </div>
                                                <p className="text-sm text-slate-600 leading-relaxed">
                                                    {getScoreExplanation(itemScore)}
                                                </p>
                                            </div>
                                        </motion.div>
                                    );
                                })}
                            </div>
                        </motion.div>

                        {/* Issues & Improvements Section with Accordions */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.8 }}
                        >
                            <div className="flex items-center gap-3 mb-6">
                                <div className="w-10 h-10 bg-rose-600 rounded-xl flex items-center justify-center shadow-md">
                                    <AlertCircle className="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <h2 className="text-2xl lg:text-3xl font-bold text-slate-900">Issues & Improvements</h2>
                                    <p className="text-sm text-slate-600 mt-1">Actionable fixes to enhance your resume</p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                {issueItems.map((item, index) => {
                                    const Icon = item.icon;
                                    const items = item.items;
                                    const isExpanded = expandedCategory === item.id;

                                    const colorClasses = {
                                        rose: {
                                            bg: 'bg-rose-50',
                                            border: 'border-rose-500',
                                            badge: 'bg-rose-500',
                                            text: 'text-rose-700',
                                            icon: 'text-rose-500',
                                        },
                                        amber: {
                                            bg: 'bg-amber-50',
                                            border: 'border-amber-500',
                                            badge: 'bg-amber-500',
                                            text: 'text-amber-700',
                                            icon: 'text-amber-500',
                                        },
                                        indigo: {
                                            bg: 'bg-indigo-50',
                                            border: 'border-indigo-500',
                                            badge: 'bg-indigo-500',
                                            text: 'text-indigo-700',
                                            icon: 'text-indigo-600',
                                        },
                                    };
                                    const colors = colorClasses[item.color as keyof typeof colorClasses] || colorClasses.indigo;

                                    if (items.length === 0) {
                                        return null;
                                    }

                                    return (
                                        <motion.div
                                            key={item.id}
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{ duration: 0.5, delay: 0.9 + index * 0.1 }}
                                            className={`bg-white rounded-2xl border-2 ${colors.border} shadow-lg ${colors.bg}`}
                                        >
                                            {/* Accordion Header - Clickable */}
                                            <button
                                                onClick={() => toggleCategory(item.id)}
                                                className="w-full p-5 lg:p-6 flex items-center justify-between hover:bg-white/50 transition-colors rounded-t-2xl"
                                                aria-expanded={isExpanded}
                                                aria-controls={`accordion-content-${item.id}`}
                                            >
                                                <div className="flex items-center gap-4 flex-1 text-left">
                                                    <div className={`w-12 h-12 rounded-xl ${colors.bg} flex items-center justify-center shadow-md border-2 ${colors.border}`}>
                                                        <Icon className={`w-6 h-6 ${colors.icon}`} />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-3">
                                                            <h3 className="text-xl lg:text-2xl font-bold text-slate-900">
                                                                {item.label}
                                                            </h3>
                                                            {item.id === 'critical' && items.length > 0 && (
                                                                <span className="text-xs font-semibold px-2 py-1 bg-rose-100 text-rose-700 rounded-full border border-rose-300">
                                                                    Fix First
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className="text-sm text-slate-600 mt-1">
                                                            {items.length} {items.length === 1 ? 'issue' : 'issues'} found
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3 ml-4 flex-shrink-0">
                                                    {item.count > 0 && (
                                                        <span
                                                            className={`w-10 h-10 rounded-full ${colors.badge} text-white text-base font-bold flex items-center justify-center shadow-lg`}
                                                        >
                                                            {item.count}
                                                        </span>
                                                    )}
                                                    <ChevronDown
                                                        className={`w-6 h-6 ${colors.icon} transition-transform duration-300 ${
                                                            isExpanded ? 'rotate-180' : ''
                                                        }`}
                                                    />
                                                </div>
                                            </button>

                                            {/* Accordion Content */}
                                            <AnimatePresence>
                                                {isExpanded && (
                                                    <motion.div
                                                        id={`accordion-content-${item.id}`}
                                                        initial={{ height: 0, opacity: 0 }}
                                                        animate={{ height: 'auto', opacity: 1 }}
                                                        exit={{ height: 0, opacity: 0 }}
                                                        transition={{ duration: 0.3, ease: 'easeInOut' }}
                                                        className="overflow-hidden"
                                                    >
                                                        <div className="px-5 lg:px-6 pb-5 lg:pb-6 space-y-4">
                                                            {items.map((issueText, issueIndex) => {
                                                                const insight = extractInsightFromIssue(issueText, item.id as 'critical' | 'warnings' | 'improvements');
                                                                
                                                                return (
                                                                    <motion.div
                                                                        key={issueIndex}
                                                                        initial={{ opacity: 0, x: -10 }}
                                                                        animate={{ opacity: 1, x: 0 }}
                                                                        transition={{ duration: 0.3, delay: issueIndex * 0.05 }}
                                                                        className="bg-white rounded-xl p-4 lg:p-5 border border-slate-200 hover:border-slate-300 shadow-md hover:shadow-lg transition-all duration-300"
                                                                    >
                                                                        {/* Issue Text */}
                                                                        <div className="flex items-start gap-3 mb-3">
                                                                            {item.id === 'critical' ? (
                                                                                <AlertOctagon className="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5" />
                                                                            ) : item.id === 'warnings' ? (
                                                                                <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                                                                            ) : (
                                                                                <Lightbulb className="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" />
                                                                            )}
                                                                            <p className="text-sm lg:text-base font-semibold text-slate-900 leading-relaxed flex-1">
                                                                                {issueText}
                                                                            </p>
                                                                        </div>

                                                                        {/* Impact */}
                                                                        <div className="ml-8 mb-3">
                                                                            <div className="flex items-center gap-2 text-xs lg:text-sm text-slate-700">
                                                                                <TrendingDown className="w-3.5 h-3.5 lg:w-4 lg:h-4 text-slate-400" />
                                                                                <span className="font-semibold">Impact:</span>
                                                                                <span>{insight.impact}</span>
                                                                            </div>
                                                                        </div>

                                                                        {/* Solution & Metadata */}
                                                                        <div className="ml-8 pt-3 border-t border-slate-100">
                                                                            <div className="flex items-start gap-2 mb-2">
                                                                                <CheckCircle2 className="w-4 h-4 lg:w-5 lg:h-5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                                                                <div className="flex-1">
                                                                                    <p className="text-xs lg:text-sm font-semibold text-slate-800 mb-1">Solution:</p>
                                                                                    <p className="text-xs lg:text-sm text-slate-700 leading-relaxed">{insight.fix}</p>
                                                                                </div>
                                                                            </div>
                                                                            <div className="ml-6 flex items-center gap-3 mt-2">
                                                                                <span className="flex items-center gap-1.5 text-xs lg:text-sm text-slate-600">
                                                                                    <Clock className="w-3 h-3 lg:w-4 lg:h-4" />
                                                                                    {insight.timeEstimate}
                                                                                </span>
                                                                                <span className={`px-2 py-1 rounded-lg text-xs lg:text-sm font-semibold ${
                                                                                    insight.difficulty === 'Easy' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' :
                                                                                    insight.difficulty === 'Medium' ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                                                                                    'bg-rose-100 text-rose-700 border border-rose-200'
                                                                                }`}>
                                                                                    {insight.difficulty}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </motion.div>
                                                                );
                                                            })}
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </motion.div>
                                    );
                                })}
                            </div>
                        </motion.div>
                    </div>
                </div>
            </div>
        </div>
    );
}
