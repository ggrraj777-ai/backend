# 🌙 Nighttime Fare Hike - Quick Start Guide

## ✅ **FEATURE COMPLETE - READY TO USE!**

A **15-25% nighttime fare hike** feature has been added to the admin panel.

---

## 🚀 **How to Configure (Takes 2 Minutes)**

### **Step 1: Access Admin Panel**
1. Go to: **Business Setup** → **Fare & Penalty Settings**
2. Or directly visit: http://127.0.0.1:8000/admin/business.setup.trip-fare

### **Step 2: Find "Nighttime Fare Hike" Section**
Look for the section with the 🌙 moon icon

### **Step 3: Configure Settings**

| Setting | What to Enter | Example |
|---------|---------------|---------|
| **Enable Nighttime Fare** | Toggle ON | ✅ Active |
| **Night Start Time** | When pricing starts | 22:00 (10 PM) |
| **Night End Time** | When pricing ends | 06:00 (6 AM) |
| **Fare Increase (%)** | Percentage (15-25%) | 20% |

### **Step 4: Save**
Click **"Submit"** button at the bottom

---

## 💡 **Example Configuration**

**Recommended for Production:**
```
✅ Enable Nighttime Fare Hike: ON
⏰ Night Start Time: 22:00 (10:00 PM)
⏰ Night End Time: 06:00 (6:00 AM)
💰 Fare Increase: 20%
```

**What This Does:**
- Any ride between 10 PM - 6 AM gets 20% surcharge
- Rs. 100 fare becomes Rs. 120
- Rs. 200 fare becomes Rs. 240

---

## 📊 **How It Works**

### **Automatic Application:**
When a customer requests a ride during nighttime hours, the system:
1. ✅ Checks if current time is within night hours
2. ✅ Adds the configured percentage to base fare
3. ✅ Shows clear breakdown to customer

### **Fare Breakdown Example:**
```
Base Fare:            Rs. 100.00
Nighttime Surcharge:   Rs. 20.00 (20%)
--------------------------------
Subtotal:             Rs. 120.00
Taxes:                 Rs. 6.00
Platform Fee:          Rs. 5.76
--------------------------------
Total:                Rs. 131.76
```

---

## ⏰ **Time Examples**

### If you set: 22:00 (10 PM) to 06:00 (6 AM)

| Ride Time | Nighttime Fare? | Why? |
|-----------|----------------|------|
| 11:30 PM | ✅ YES | Between 10 PM - 6 AM |
| 2:00 AM | ✅ YES | Between 10 PM - 6 AM |
| 5:30 AM | ✅ YES | Between 10 PM - 6 AM |
| 6:00 AM | ❌ NO | Nighttime ends at 6 AM |
| 2:00 PM | ❌ NO | Daytime |
| 9:30 PM | ❌ NO | Before 10 PM |

---

## 📱 **User Experience**

### **In Customer App:**
- Clear "Nighttime Surcharge" line item
- Percentage shown for transparency
- Applied automatically, no action needed

### **In Driver App:**
- Higher fares displayed
- Incentive to work night shifts
- Fair compensation for late hours

---

## 🎯 **Benefits**

### **For Business:**
- ✅ Industry-standard pricing (like Uber, Ola)
- ✅ Balances supply-demand at night
- ✅ Increases revenue during low-volume hours

### **For Drivers:**
- ✅ Extra earnings for night work
- ✅ Motivation to accept late rides
- ✅ Fair compensation

### **For Customers:**
- ✅ Better driver availability at night
- ✅ Transparent pricing
- ✅ Predictable surcharge

---

## 💼 **Recommended Settings by City Type**

| City Type | Recommended % | Start Time | End Time |
|-----------|---------------|------------|----------|
| **Small Cities** | 15% | 23:00 | 06:00 |
| **Medium Cities** | 20% | 22:00 | 06:00 |
| **Large Cities** | 25% | 22:00 | 07:00 |
| **Metro Cities** | 20-25% | 22:00 | 06:00 |

---

## 🔧 **Testing**

### **Quick Test:**
1. Enable nighttime fare in admin panel
2. Request a test ride in the app
3. Check if surcharge appears in fare breakdown

### **Manual Calculation:**
```
If Percentage = 20%
Base Fare = Rs. 100

Nighttime Charge = Rs. 100 × 20% = Rs. 20
Total Fare = Rs. 100 + Rs. 20 = Rs. 120
```

---

## 📋 **Checklist**

- [ ] Access admin panel
- [ ] Navigate to Fare & Penalty Settings
- [ ] Enable nighttime fare toggle
- [ ] Set start time (e.g., 22:00)
- [ ] Set end time (e.g., 06:00)
- [ ] Set percentage (15-25%)
- [ ] Click Submit
- [ ] Test with a ride during night hours

---

## ❓ **FAQ**

**Q: Can I change the percentage later?**
A: Yes! Just go back to settings and update anytime.

**Q: What if I want different hours for weekends?**
A: Currently uses same hours daily. Can be enhanced if needed.

**Q: Does this work with zone-based pricing?**
A: Yes! Nighttime surcharge works with all pricing models.

**Q: Can I disable it temporarily?**
A: Yes! Just toggle OFF the "Enable Nighttime Fare" switch.

**Q: Will existing rides be affected?**
A: Only new rides. Ongoing rides use the fare calculated at booking.

---

## 🎉 **You're All Set!**

The nighttime fare feature is now ready. Just:
1. Configure in admin panel (2 minutes)
2. Save settings
3. Rides during night hours will automatically get the surcharge

**Location:** Business Setup → Fare & Penalty Settings

**Need Help?** Check the tooltips (ℹ️ icons) next to each field!

---

## 📸 **Visual Guide**

### What You'll See in Admin Panel:

```
┌─────────────────────────────────────────────┐
│ 🌙 Nighttime Fare Hike                      │
├─────────────────────────────────────────────┤
│ ☐ Enable Nighttime Fare Hike               │
│                                              │
│ Night Start Time:  [22:00] ℹ️               │
│                                              │
│ Night End Time:    [06:00] ℹ️               │
│                                              │
│ Fare Increase (%): [20   ] ℹ️               │
│ Recommended: 15-25%                          │
│                                              │
│              [Submit Button]                 │
└─────────────────────────────────────────────┘
```

---

**Happy Pricing! 🚗💰**

