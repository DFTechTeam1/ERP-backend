<?php

return [
    'coa' => [
        'locked' => 'This is a system account. :field cannot be changed.',
        'lockedGeneric' => 'This is a system account and cannot be changed.',
        'hasTransactions' => 'This account has posted transactions. Its :field can no longer be changed.',
        'parentNotFound' => 'Parent account not found.',
        'parentNotHeader' => 'You can only nest an account under a header, not another posting account.',
        'parentTypeMismatch' => 'The parent must be the same account type.',
        'reparentTypeMismatch' => 'The new group must be the same account type.',
        'reparentOntoPostable' => 'You can only move an account under a header, not another posting account.',
        'reparentCycle' => 'Cannot move an account under itself.',
        'hasBalance' => 'This account still has a balance. Clear it before deactivating.',
        'hasChildren' => 'Remove or move its sub-accounts before deleting this group.',
        'leafToHeaderWithTxns' => 'This account already has entries, so it cannot become a header group.',
        'reparentWarning' => 'Historical report grouping will shift.',
        'saved' => 'Account saved',
        'activated' => 'Account activated',
        'deactivated' => 'Account deactivated',
        'deleted' => 'Account deleted',
    ],
    'field' => [
        'code' => 'Account code',
        'account_type' => 'Type',
        'account_subtype' => 'Subtype',
        'parent' => 'Parent',
    ],
];
