<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Full list of country dial codes for the dropdowns.
     */
    private function countryCodes(): array
    {
        return [
            ['code' => '+93',  'name' => 'Afghanistan'],
            ['code' => '+355', 'name' => 'Albania'],
            ['code' => '+213', 'name' => 'Algeria'],
            ['code' => '+376', 'name' => 'Andorra'],
            ['code' => '+244', 'name' => 'Angola'],
            ['code' => '+54',  'name' => 'Argentina'],
            ['code' => '+374', 'name' => 'Armenia'],
            ['code' => '+61',  'name' => 'Australia'],
            ['code' => '+43',  'name' => 'Austria'],
            ['code' => '+994', 'name' => 'Azerbaijan'],
            ['code' => '+973', 'name' => 'Bahrain'],
            ['code' => '+880', 'name' => 'Bangladesh'],
            ['code' => '+32',  'name' => 'Belgium'],
            ['code' => '+501', 'name' => 'Belize'],
            ['code' => '+229', 'name' => 'Benin'],
            ['code' => '+975', 'name' => 'Bhutan'],
            ['code' => '+591', 'name' => 'Bolivia'],
            ['code' => '+387', 'name' => 'Bosnia and Herzegovina'],
            ['code' => '+267', 'name' => 'Botswana'],
            ['code' => '+55',  'name' => 'Brazil'],
            ['code' => '+673', 'name' => 'Brunei'],
            ['code' => '+359', 'name' => 'Bulgaria'],
            ['code' => '+226', 'name' => 'Burkina Faso'],
            ['code' => '+257', 'name' => 'Burundi'],
            ['code' => '+855', 'name' => 'Cambodia'],
            ['code' => '+237', 'name' => 'Cameroon'],
            ['code' => '+1',   'name' => 'Canada'],
            ['code' => '+238', 'name' => 'Cape Verde'],
            ['code' => '+236', 'name' => 'Central African Republic'],
            ['code' => '+235', 'name' => 'Chad'],
            ['code' => '+56',  'name' => 'Chile'],
            ['code' => '+86',  'name' => 'China'],
            ['code' => '+57',  'name' => 'Colombia'],
            ['code' => '+269', 'name' => 'Comoros'],
            ['code' => '+243', 'name' => 'Congo (DRC)'],
            ['code' => '+242', 'name' => 'Congo (Republic)'],
            ['code' => '+506', 'name' => 'Costa Rica'],
            ['code' => '+385', 'name' => 'Croatia'],
            ['code' => '+53',  'name' => 'Cuba'],
            ['code' => '+357', 'name' => 'Cyprus'],
            ['code' => '+420', 'name' => 'Czech Republic'],
            ['code' => '+45',  'name' => 'Denmark'],
            ['code' => '+253', 'name' => 'Djibouti'],
            ['code' => '+1',   'name' => 'Dominican Republic'],
            ['code' => '+593', 'name' => 'Ecuador'],
            ['code' => '+20',  'name' => 'Egypt'],
            ['code' => '+503', 'name' => 'El Salvador'],
            ['code' => '+240', 'name' => 'Equatorial Guinea'],
            ['code' => '+291', 'name' => 'Eritrea'],
            ['code' => '+372', 'name' => 'Estonia'],
            ['code' => '+251', 'name' => 'Ethiopia'],
            ['code' => '+679', 'name' => 'Fiji'],
            ['code' => '+358', 'name' => 'Finland'],
            ['code' => '+33',  'name' => 'France'],
            ['code' => '+241', 'name' => 'Gabon'],
            ['code' => '+220', 'name' => 'Gambia'],
            ['code' => '+995', 'name' => 'Georgia'],
            ['code' => '+49',  'name' => 'Germany'],
            ['code' => '+233', 'name' => 'Ghana'],
            ['code' => '+30',  'name' => 'Greece'],
            ['code' => '+502', 'name' => 'Guatemala'],
            ['code' => '+224', 'name' => 'Guinea'],
            ['code' => '+245', 'name' => 'Guinea-Bissau'],
            ['code' => '+592', 'name' => 'Guyana'],
            ['code' => '+509', 'name' => 'Haiti'],
            ['code' => '+504', 'name' => 'Honduras'],
            ['code' => '+852', 'name' => 'Hong Kong'],
            ['code' => '+36',  'name' => 'Hungary'],
            ['code' => '+354', 'name' => 'Iceland'],
            ['code' => '+91',  'name' => 'India'],
            ['code' => '+62',  'name' => 'Indonesia'],
            ['code' => '+98',  'name' => 'Iran'],
            ['code' => '+964', 'name' => 'Iraq'],
            ['code' => '+353', 'name' => 'Ireland'],
            ['code' => '+972', 'name' => 'Israel'],
            ['code' => '+39',  'name' => 'Italy'],
            ['code' => '+225', 'name' => 'Ivory Coast'],
            ['code' => '+1',   'name' => 'Jamaica'],
            ['code' => '+81',  'name' => 'Japan'],
            ['code' => '+962', 'name' => 'Jordan'],
            ['code' => '+7',   'name' => 'Kazakhstan'],
            ['code' => '+254', 'name' => 'Kenya'],
            ['code' => '+965', 'name' => 'Kuwait'],
            ['code' => '+996', 'name' => 'Kyrgyzstan'],
            ['code' => '+856', 'name' => 'Laos'],
            ['code' => '+371', 'name' => 'Latvia'],
            ['code' => '+961', 'name' => 'Lebanon'],
            ['code' => '+266', 'name' => 'Lesotho'],
            ['code' => '+231', 'name' => 'Liberia'],
            ['code' => '+218', 'name' => 'Libya'],
            ['code' => '+423', 'name' => 'Liechtenstein'],
            ['code' => '+370', 'name' => 'Lithuania'],
            ['code' => '+352', 'name' => 'Luxembourg'],
            ['code' => '+853', 'name' => 'Macau'],
            ['code' => '+389', 'name' => 'Macedonia'],
            ['code' => '+261', 'name' => 'Madagascar'],
            ['code' => '+265', 'name' => 'Malawi'],
            ['code' => '+60',  'name' => 'Malaysia'],
            ['code' => '+960', 'name' => 'Maldives'],
            ['code' => '+223', 'name' => 'Mali'],
            ['code' => '+356', 'name' => 'Malta'],
            ['code' => '+222', 'name' => 'Mauritania'],
            ['code' => '+230', 'name' => 'Mauritius'],
            ['code' => '+52',  'name' => 'Mexico'],
            ['code' => '+373', 'name' => 'Moldova'],
            ['code' => '+377', 'name' => 'Monaco'],
            ['code' => '+976', 'name' => 'Mongolia'],
            ['code' => '+382', 'name' => 'Montenegro'],
            ['code' => '+212', 'name' => 'Morocco'],
            ['code' => '+258', 'name' => 'Mozambique'],
            ['code' => '+95',  'name' => 'Myanmar'],
            ['code' => '+264', 'name' => 'Namibia'],
            ['code' => '+977', 'name' => 'Nepal'],
            ['code' => '+31',  'name' => 'Netherlands'],
            ['code' => '+64',  'name' => 'New Zealand'],
            ['code' => '+505', 'name' => 'Nicaragua'],
            ['code' => '+227', 'name' => 'Niger'],
            ['code' => '+234', 'name' => 'Nigeria'],
            ['code' => '+47',  'name' => 'Norway'],
            ['code' => '+968', 'name' => 'Oman'],
            ['code' => '+92',  'name' => 'Pakistan'],
            ['code' => '+507', 'name' => 'Panama'],
            ['code' => '+675', 'name' => 'Papua New Guinea'],
            ['code' => '+595', 'name' => 'Paraguay'],
            ['code' => '+51',  'name' => 'Peru'],
            ['code' => '+63',  'name' => 'Philippines'],
            ['code' => '+48',  'name' => 'Poland'],
            ['code' => '+351', 'name' => 'Portugal'],
            ['code' => '+974', 'name' => 'Qatar'],
            ['code' => '+40',  'name' => 'Romania'],
            ['code' => '+7',   'name' => 'Russia'],
            ['code' => '+250', 'name' => 'Rwanda'],
            ['code' => '+966', 'name' => 'Saudi Arabia'],
            ['code' => '+221', 'name' => 'Senegal'],
            ['code' => '+381', 'name' => 'Serbia'],
            ['code' => '+232', 'name' => 'Sierra Leone'],
            ['code' => '+65',  'name' => 'Singapore'],
            ['code' => '+421', 'name' => 'Slovakia'],
            ['code' => '+386', 'name' => 'Slovenia'],
            ['code' => '+252', 'name' => 'Somalia'],
            ['code' => '+27',  'name' => 'South Africa'],
            ['code' => '+82',  'name' => 'South Korea'],
            ['code' => '+211', 'name' => 'South Sudan'],
            ['code' => '+34',  'name' => 'Spain'],
            ['code' => '+94',  'name' => 'Sri Lanka'],
            ['code' => '+249', 'name' => 'Sudan'],
            ['code' => '+597', 'name' => 'Suriname'],
            ['code' => '+268', 'name' => 'Swaziland'],
            ['code' => '+46',  'name' => 'Sweden'],
            ['code' => '+41',  'name' => 'Switzerland'],
            ['code' => '+963', 'name' => 'Syria'],
            ['code' => '+886', 'name' => 'Taiwan'],
            ['code' => '+992', 'name' => 'Tajikistan'],
            ['code' => '+255', 'name' => 'Tanzania'],
            ['code' => '+66',  'name' => 'Thailand'],
            ['code' => '+228', 'name' => 'Togo'],
            ['code' => '+216', 'name' => 'Tunisia'],
            ['code' => '+90',  'name' => 'Turkey'],
            ['code' => '+993', 'name' => 'Turkmenistan'],
            ['code' => '+256', 'name' => 'Uganda'],
            ['code' => '+380', 'name' => 'Ukraine'],
            ['code' => '+971', 'name' => 'United Arab Emirates'],
            ['code' => '+44',  'name' => 'United Kingdom'],
            ['code' => '+1',   'name' => 'United States'],
            ['code' => '+598', 'name' => 'Uruguay'],
            ['code' => '+998', 'name' => 'Uzbekistan'],
            ['code' => '+58',  'name' => 'Venezuela'],
            ['code' => '+84',  'name' => 'Vietnam'],
            ['code' => '+967', 'name' => 'Yemen'],
            ['code' => '+260', 'name' => 'Zambia'],
            ['code' => '+263', 'name' => 'Zimbabwe'],
        ];
    }

    /**
     * List all users with search and pagination.
     */
    public function index(Request $request)
    {
        $perPage = in_array($request->per_page, [10, 25, 50, 100]) ? (int) $request->per_page : 10;
        $search  = $request->search;

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortable = ['name' => 'name', 'email' => 'email', 'role' => 'role', 'created_at' => 'created_at'];
        $sortCol = $request->sort ?? 'created_at';
        $sortDir = $request->direction === 'asc' ? 'asc' : 'desc';
        if (isset($sortable[$sortCol])) {
            $query->orderBy($sortable[$sortCol], $sortDir);
        } else {
            $query->latest();
        }
        $users        = $query->paginate($perPage)->withQueryString();
        $countryCodes = $this->countryCodes();
        $currentSort  = $sortCol;
        $currentDir   = $sortDir;
        return view('user-management.index', compact('users', 'perPage', 'search', 'countryCodes', 'currentSort', 'currentDir'));
    }

    /**
     * Normalise a role string to Title Case for DB storage.
     * e.g. 'admin' => 'Admin', 'super_admin' => 'Super Admin', 'user' => 'User'
     */
    private function formatRole(string $role): string
    {
        return ucwords(str_replace('_', ' ', strtolower($role)));
    }

    /**
     * Create a new user.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'role'         => 'required|in:admin,user,super_admin',
            'phone'        => 'nullable|string|max:30',
            'country_code' => 'nullable|string|max:10',
            'patient_id'   => 'nullable|integer',
        ]);

        User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => bcrypt($request->password),
            'role'         => $this->formatRole($request->role),
            'phone'        => $request->phone,
            'country_code' => $request->country_code,
            'patient_id'   => ($request->role === 'user' && $request->patient_id) ? $request->patient_id : null,
            'is_active'    => true,
        ]);

        return back()->with('success', 'User created successfully.');
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $user->id,
            'role'         => 'required|in:admin,user,super_admin',
            'phone'        => 'nullable|string|max:30',
            'country_code' => 'nullable|string|max:10',
            'password'     => 'nullable|string|min:8|confirmed',
        ]);

        $data = [
            'name'         => $request->name,
            'email'        => $request->email,
            'role'         => $this->formatRole($request->role),
            'phone'        => $request->phone,
            'country_code' => $request->country_code,
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Toggle the is_active status of a user (AJAX).
     */
    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'status'    => 'success',
            'is_active' => $user->is_active,
            'message'   => 'User status updated.',
        ]);
    }

    /**
     * Soft-delete a user.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            if (request()->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'You cannot delete your own account.'], 403);
            }
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'User deleted.']);
        }

        return back()->with('success', 'User deleted successfully.');
    }
}
