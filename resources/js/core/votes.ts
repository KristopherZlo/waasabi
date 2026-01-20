export const normalizeCommentVote = (value: unknown) => {
    if (value === 1 || value === -1) {
        return value;
    }
    if (value === '1') {
        return 1;
    }
    if (value === '-1') {
        return -1;
    }
    return 0;
};
